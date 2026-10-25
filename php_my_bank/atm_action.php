<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// 1. 登录拦截
if (!isset($_SESSION['card_number'])) {
    echo json_encode(['code' => 401, 'message' => '未登录或登录已失效']);
    exit;
}

$myCard = $_SESSION['card_number'];

// 2. 接收前端传过来的操作类型(deposit=存, withdraw=取)和金额
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$action = $data['action'] ?? ''; // 'deposit' 或 'withdraw'
$amount = floatval($data['amount'] ?? 0);

if ($amount <= 0 || !in_array($action, ['deposit', 'withdraw'])) {
    echo json_encode(['code' => 400, 'message' => '参数错误或金额无效']);
    exit;
}

// 3. 连接数据库
$db = new mysqli('localhost', 'root', 'root', 'virtual_bank');
if ($db->connect_error) {
    echo json_encode(['code' => 500, 'message' => '核心账务节点连接失败']);
    exit;
}
$db->set_charset('utf8mb4');

try {
    // 开启事务保护
    $db->begin_transaction();

    // 4. 先查询并锁住当前账户余额
    $stmt = $db->prepare("SELECT balance FROM accounts WHERE card_number = ? FOR UPDATE");
    $stmt->bind_param("s", $myCard);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $current_balance = floatval($res['balance'] ?? 0);

    if ($action === 'withdraw' && $current_balance < $amount) {
        throw new Exception("核心拒绝：卡内可用余额不足");
    }

    // 5. 根据操作类型加钱或减钱
    if ($action === 'deposit') {
        $update_stmt = $db->prepare("UPDATE accounts SET balance = balance + ? WHERE card_number = ?");
        $type_text = '📥 现金存款';
        $remark = 'ATM自助机现金存款';
    } else {
        $update_stmt = $db->prepare("UPDATE accounts SET balance = balance - ? WHERE card_number = ?");
        $type_text = '📤 现金取款';
        $remark = 'ATM自助机现金取款';
    }
    $update_stmt->bind_param("ds", $amount, $myCard);
    $update_stmt->execute();

    // 6. 核心加分点：顺手往你的流水表里写一笔审计记录！这样大盘刷出来才好看
    $log_stmt = $db->prepare("INSERT INTO transactions (source_account, target_account, amount, type, remark) VALUES (?, ?, ?, ?, ?)");
    // 存取款由于是自己对自己，付款方和收款方都写自己卡号即可
    $log_stmt->bind_param("ssdss", $myCard, $myCard, $amount, $action, $remark);
    $log_stmt->execute();

    // 提交事务
    $db->commit();

    echo json_encode(['code' => 200, 'message' => ($action === 'deposit' ? '存款成功' : '取款成功')]);

} catch (Exception $e) {
    $db->rollback();
    echo json_encode(['code' => 500, 'message' => $e->getMessage()]);
}