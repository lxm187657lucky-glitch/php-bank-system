<?php
// 1. 开启 Session 守护
session_start();
header('Content-Type: application/json; charset=utf-8');

// 2. 安全拦截：确保用户已登录
if (!isset($_SESSION['card_number'])) {
    echo json_encode(['code' => 401, 'message' => '未登录或登录已失效']);
    exit;
}

$myCard = $_SESSION['card_number'];
$myName = $_SESSION['username'];

// 3. 连接核心数据库
$db = new mysqli('localhost', 'root', 'root', 'virtual_bank');
if ($db->connect_error) {
    echo json_encode(['code' => 500, 'message' => '核心账务节点连接失败']);
    exit;
}
$db->set_charset('utf8mb4');

// 4. 查询当前用户的最新可用余额
$stmt = $db->prepare("SELECT balance FROM accounts WHERE card_number = ?");
if (!$stmt) {
    echo json_encode([
        'code' => 500, 
        'message' => 'SQL预处理失败，请检查数据库状态。MySQL错误详情: ' . $db->error
    ]);
    exit;
}

$stmt->bind_param("s", $myCard);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$myBalance = $res['balance'] ?? 0.00;

// 5. 抓取最近 5 笔交易审计流水
$trade_history = [];
$table_check = $db->query("SHOW TABLES LIKE 'transactions'");
if ($table_check && $table_check->num_rows > 0) {
    
    // 💡 核心修复：这里直接查找您原有的数据库字段 source_account 和 target_account
    $log_stmt = $db->prepare("SELECT * FROM transactions WHERE source_account = ? OR target_account = ? ORDER BY id DESC LIMIT 5");
    
    if ($log_stmt) {
        $log_stmt->bind_param("ss", $myCard, $myCard);
        $log_stmt->execute();
        $result_all = $log_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($result_all as $trade) {
            // 💡 核心修复：这里的键名提取也同步换回 account 后缀
            $is_sender = ($trade['source_account'] === $myCard);
            $oppo_card = $is_sender ? $trade['target_account'] : $trade['source_account'];
            
            // 对对方卡号进行脱敏隐藏处理
            $masked_card = strlen($oppo_card) > 10 ? substr($oppo_card, 0, 6) . '****' . substr($oppo_card, -4) : $oppo_card;
            
            $trade_history[] = [
                'type' => $is_sender ? '📤 跨行转出' : '📥 跨行转入',
                'oppo_card' => $masked_card,
                'amount' => ($is_sender ? '-' : '+') . '￥' . number_format($trade['amount'], 2),
                'amount_class' => $is_sender ? 'status-minus' : 'status-plus',
                'created_at' => $trade['created_at']
            ];
        }
    }
}

// 6. 打包所有数据，统一输出给前端
echo json_encode([
    'code' => 200,
    'username' => $myName,
    'card_number' => $myCard,
    'balance' => number_format($myBalance, 2),
    'history' => $trade_history
]);