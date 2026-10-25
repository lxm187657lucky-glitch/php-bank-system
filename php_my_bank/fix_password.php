<?php
header('Content-Type: text/html; charset=utf-8');

$dsn = "mysql:host=localhost;dbname=virtual_bank;charset=utf8mb4";
$dbUser = "root";
$dbPass = "root";

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // 1. 让 PHP 内置的安全算法直接在服务器内生成官方密文
    $securePassword = password_hash('123456', PASSWORD_DEFAULT);

    // 2. 直接通过预处理语句（PDO prepare）写入数据库，完美隔绝转义干扰
    $stmt = $pdo->prepare("UPDATE users SET password = :pass WHERE id IN (1, 2)");
    $stmt->execute([':pass' => $securePassword]);

    echo "<h2 style='color:green;'>🎉 密码在服务器内部加密并重置成功！</h2>";
    echo "<p>当前生成的标准密文为：<code>" . $securePassword . "</code></p>";
    echo "<p>现在请立刻去 <a href='login.html'>login.html</a> 页面重新登录试试！</p>";

} catch (Exception $e) {
    echo "<h2 style='color:red;'>❌ 重置失败：</h2>" . $e->getMessage();
}
