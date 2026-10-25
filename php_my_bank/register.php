<?php
header('Content-Type: application/json; charset=utf-8');

// 1. 获取前端传过来的 JSON 数据
$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);

$username = trim($data['username'] ?? '');
$password = trim($data['password'] ?? '');

if (empty($username) || empty($password)) {
    echo json_encode(['code' => 400, 'message' => '用户名或密码不能为空']);
    exit;
}

// 2. 连接数据库（已配置正确的 root 密码和 virtual_bank 库）
$db = new mysqli('localhost', 'root', 'root', 'virtual_bank');
if ($db->connect_error) {
    echo json_encode(['code' => 500, 'message' => '数据库连接失败']);
    exit;
}
$db->set_charset('utf8mb4');

// 3. 检查用户名是否已经被抢注
$stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['code' => 400, 'message' => '该用户名已被占用，请换一个']);
    exit;
}

// 4. 使用 password_hash 算法进行强加密
$hashed_password = password_hash($password, PASSWORD_BCRYPT);

// 5. 随机生成一个 19 位的标准银行卡号 (以622202开头)
$random_bits = '';
for ($i = 0; $i < 13; $i++) {
    $random_bits .= rand(0, 9);
}
$generated_card = '622202' . $random_bits;

// 6. 开启数据库事务，确保两张表要么同时成功，要么同时失败
$db->begin_transaction();

try {
    // 1. 写入 users 表
    $stmt1 = $db->prepare("INSERT INTO users (username, password, card_number, real_name) VALUES (?, ?, ?, ?)");
    $stmt1->bind_param("ssss", $username, $hashed_password, $generated_card, $username);
    $stmt1->execute();
    
    // 💡 核心魔改：获取刚刚插入的用户的自增 ID (也就是 user_id)
    $new_user_id = $db->insert_id; 
    
    // 2. 写入 accounts 表，把必需的 user_id、card_number、balance 全部带上
    // 顺便把状态 status 也默认给 1（正常）
    $initial_balance = 10000.00;
    $default_status = 1;
    
    $stmt2 = $db->prepare("INSERT INTO accounts (user_id, card_number, balance, status) VALUES (?, ?, ?, ?)");
    // 对应变量类型：i (整数), s (字符串), d (浮点数), i (整数) -> "isdi"
    $stmt2->bind_param("isdi", $new_user_id, $generated_card, $initial_balance, $default_status);
    $stmt2->execute();
    
    // 3. 提交事务
    $db->commit();
    
    echo json_encode([
        'code' => 200,
        'message' => '开户成功',
        'card_number' => $generated_card
    ]);

} catch (Exception $e) {
    $db->rollback(); // 发生异常，回滚全部操作
    echo json_encode(['code' => 500, 'message' => '开户核心失败，事务已回滚。原因: ' . $e->getMessage()]);
}