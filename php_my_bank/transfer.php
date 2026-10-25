<?php
// 1. 响应头设置：告诉前端我们返回的是标准 JSON 格式
header('Content-Type: application/json; charset=utf-8');

// 2. 接收前端通过 Fetch 传过来的转账数据（JSON 格式）
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

// 自动兼容两种前端传参命名习惯（source_card 或 from_card）
$fromCard = $data['source_card'] ?? $data['from_card'] ?? '';
$toCard   = $data['target_card'] ?? $data['to_card'] ?? '';
$amount   = floatval($data['amount'] ?? 0); // 转账金额

// 基础验证：金额必须大于 0，且两边卡号不能为空，也不能自己转给自己
if (empty($fromCard) || empty($toCard) || $amount <= 0 || $fromCard === $toCard) {
    echo json_encode([
        'code' => 400,
        'message' => '交易参数错误或卡号相同。当前解析值：付款方[' . $fromCard . '], 收款方[' . $toCard . '], 金额[' . $amount . ']'
    ]);
    exit;
}

// 3. 连接小皮面板的 MySQL (使用 PDO 扩展)
$dsn = "mysql:host=localhost;dbname=virtual_bank;charset=utf8mb4";
$username = "root";
$password = "root";

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // =================【核心技术亮点：开启事务】=================
    $pdo->beginTransaction();

    // 4. 步骤一：查询并【锁定】付款方账户（行级悲观锁）
    $stmt = $pdo->prepare("SELECT balance, status FROM accounts WHERE card_number = :card FOR UPDATE");
    $stmt->execute([':card' => $fromCard]);
    $fromAccount = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fromAccount) {
        throw new Exception("付款方卡号不存在");
    }
    if ($fromAccount['status'] != 1) {
        throw new Exception("付款方账户已被冻结");
    }
    if ($fromAccount['balance'] < $amount) {
        throw new Exception("账户可用余额不足");
    }

    // 5. 步骤二：查询并【锁定】收款方账户
    $stmt = $pdo->prepare("SELECT status FROM accounts WHERE card_number = :card FOR UPDATE");
    $stmt->execute([':card' => $toCard]);
    $toAccount = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$toAccount) {
        throw new Exception("收款方卡号不存在，请仔细核对");
    }
    if ($toAccount['status'] != 1) {
        throw new Exception("收款方账户状态异常，暂不可接收资金");
    }

    // 6. 步骤三：扣减付款方余额
    $stmt = $pdo->prepare("UPDATE accounts SET balance = balance - :amount WHERE card_number = :card");
    $stmt->execute([':amount' => $amount, ':card' => $fromCard]);

    // 7. 步骤四：增加收款方余额
    $stmt = $pdo->prepare("UPDATE accounts SET balance = balance + :amount WHERE card_number = :card");
    $stmt->execute([':amount' => $amount, ':card' => $toCard]);

    // 8. 步骤五：录入交易流水日志（💡 完美对接数据库字段名：source_account 与 target_account）
    $stmt = $pdo->prepare("INSERT INTO transactions (source_account, target_account, amount, type, remark) 
                           VALUES (:source, :target, :amount, 'transfer', '网上银行转账')");
    $stmt->execute([
        ':source' => $fromCard,
        ':target' => $toCard,
        ':amount' => $amount
    ]);

    // =================【核心技术亮点：提交事务】=================
    $pdo->commit();

    echo json_encode([
        'code' => 200,
        'message' => '转账成功',
        'data' => ['serial_no' => $pdo->lastInsertId()] // 返回流水号给前端回单使用
    ]);
} catch (Exception $e) {
    // =================【核心技术亮点：事务回滚】=================
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'code' => 500,
        'message' => '核心清算拒绝：' . $e->getMessage()
    ]);
}
