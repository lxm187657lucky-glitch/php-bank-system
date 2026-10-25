<?php
// 1. 开启 Session 守护
session_start();
header('Content-Type: application/json; charset=utf-8');

// 2. 获取前端传过来的登录数据（支持表单或 JSON）
$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);

$username = trim($data['username'] ?? ($_POST['username'] ?? ''));
$password = trim($data['password'] ?? ($_POST['password'] ?? ''));

if (empty($username) || empty($password)) {
    echo json_encode(['code' => 400, 'message' => '用户名或密码不能为空']);
    exit;
}

// 3. 连接数据库（配置正确的 root 密码和库名）
$db = new mysqli('localhost', 'root', 'root', 'virtual_bank');
if ($db->connect_error) {
    echo json_encode(['code' => 500, 'message' => '数据库连接失败']);
    exit;
}
$db->set_charset('utf8mb4');

// 4. 核心安全查询：只通过用户名查找用户
$stmt = $db->prepare("SELECT id, username, password, card_number FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    
    // 5. 智能密码验证：同时兼容新版 password_hash 加密和老版明文密码
    $password_valid = false;
    
    if (password_verify($password, $user['password'])) {
        // 如果是新注册的用户（哈希加密），验证通过
        $password_valid = true;
    } else if ($password === $user['password']) {
        // 如果是数据库里原本就有的老用户（明文存储），也让它通过
        $password_valid = true;
    }

    if ($password_valid) {
        // 6. 验证成功，将关键数据灌入 Session 缓存，供看板和转账页使用
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        
        // 【核心同步】确保把我们刚刚在 users 表里加的 card_number 塞进 Session
        $_SESSION['card_number'] = $user['card_number'];

        echo json_encode(['code' => 200, 'message' => '登录成功']);
        exit;
    }
}

// 7. 账号不存在或密码错误统一返回
echo json_encode(['code' => 401, 'message' => '用户名或核心交易密码错误']);