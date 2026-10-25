<?php
// 1. 必须先开启 session
session_start();

// 2. 如果发现没登录，直接大门紧锁，强制踢回登录页！
if (!isset($_SESSION['card_number'])) {
    header("Location: login.html");
    exit;
}

// 3. 拿到当前登录用户的安全卡号和用户名
$myCard = $_SESSION['card_number'];
$myName = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>虚拟银行 - 核心转账安全系统</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f4f7f6; padding: 40px 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        
        .main-container { width: 100%; max-width: 900px; display: flex; gap: 30px; }
        
        /* 左侧：表单主卡片 */
        .form-card { flex: 1.5; background: white; padding: 35px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); position: relative; }
        .form-card h2 { color: #1b263b; font-size: 24px; margin-bottom: 5px; display: flex; align-items: center; gap: 10px; }
        .welcome-text { font-size: 14px; color: #666; margin-bottom: 25px; }
        .welcome-text strong { color: #2196F3; }

        /* 银行业务步骤条（假步骤，显专业） */
        .step-bar { display: flex; justify-content: space-between; margin-bottom: 30px; background: #f8f9fa; padding: 12px; border-radius: 8px; }
        .step { font-size: 12px; color: #999; font-weight: bold; }
        .step.active { color: #2196F3; }

        /* 表单输入框美化 */
        .input-box { margin-bottom: 20px; }
        .input-box label { display: block; font-size: 13px; color: #415a77; font-weight: 600; margin-bottom: 8px; }
        .input-box input {
            width: 100%; padding: 12px 14px; border: 2px solid #e0e1dd; border-radius: 8px;
            font-size: 15px; color: #1b263b; transition: all 0.3s ease; background-color: #fafafa;
        }
        .input-box input[readonly] { background-color: #edf2f4; color: #777; cursor: not-allowed; border-color: #e0e1dd; }
        .input-box input:focus:not([readonly]) { border-color: #2196F3; background-color: #fff; outline: none; box-shadow: 0 0 10px rgba(33, 150, 243, 0.1); }

        .submit-btn {
            width: 100%; padding: 14px; background: linear-gradient(135deg, #2196F3, #1E88E5);
            color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: bold;
            cursor: pointer; box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3); transition: all 0.2s ease; margin-top: 10px;
        }
        .submit-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(33, 150, 243, 0.5); }

        /* 右侧：安全防诈骗提示卡片 */
        .notice-card { flex: 1; background: linear-gradient(180deg, #1b263b, #0d1b2a); padding: 30px; border-radius: 12px; color: #e0e1dd; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .notice-card h3 { color: #fff; font-size: 16px; margin-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; }
        .notice-card p { font-size: 13px; line-height: 1.7; margin-bottom: 15px; opacity: 0.8; }
        .back-link { display: block; text-align: center; color: #2196F3; text-decoration: none; font-size: 14px; font-weight: bold; margin-top: 20px; transition: color 0.3s; }
        .back-link:hover { color: #64b5f6; text-decoration: underline; }

        /* === 核心：弹出式电子回单样式 === */
        .receipt-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5);
            display: none; justify-content: center; align-items: center; z-index: 1000; backdrop-filter: blur(4px);
        }
        .receipt-box {
            background: white; width: 100%; max-width: 450px; padding: 30px; border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15); position: relative; border-top: 6px solid #4CAF50;
        }
        .receipt-title { text-align: center; color: #4CAF50; font-size: 20px; font-weight: bold; margin-bottom: 25px; }
        .receipt-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px dashed #eee; font-size: 14px; }
        .receipt-label { color: #666; }
        .receipt-value { color: #333; font-weight: 500; }
        /* 虚拟红色公章 */
        .bank-seal {
            position: absolute; right: 40px; bottom: 80px; width: 100px; height: 100px;
            border: 3px solid rgba(244, 67, 54, 0.6); border-radius: 50%; color: rgba(244, 67, 54, 0.6);
            text-align: center; line-height: 1.3; font-size: 12px; font-weight: bold;
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            transform: rotate(-15deg); letter-spacing: 1px; pointer-events: none;
        }
        .close-receipt-btn {
            width: 100%; padding: 12px; background: #f5f5f5; border: none; border-radius: 6px;
            color: #555; font-size: 14px; font-weight: bold; cursor: pointer; margin-top: 25px; transition: background 0.2s;
        }
        .close-receipt-btn:hover { background: #e0e0e0; }
    </style>
</head>
<body>

<div class="main-container">
    <div class="form-card">
        <h2>🏦 核心转账交易系统</h2>
        <p class="welcome-text">系统操作员：<strong><?php echo htmlspecialchars($myName); ?></strong></p>
        
        <div class="step-bar">
            <span class="step active">① 录入指令</span>
            <span class="step active">② 校验重定向</span>
            <span class="step" id="step3">③ 电子回单确认</span>
        </div>

        <form id="transferForm">
            <div class="input-box">
                <label>💳 付款方安全卡号 (只读守护)</label>
                <input type="text" id="source_account" value="<?php echo $myCard; ?>" readonly>
            </div>
            <div class="input-box">
                <label>🎯 收款方借记卡号</label>
                <input type="text" id="target_account" placeholder="请输入对方的 19 位卡号" value="6222020000000000002">
            </div>
            <div class="input-box">
                <label>💰 核心清算金额 (CNY)</label>
                <input type="number" id="amount" placeholder="请输入转账金额" value="100">
            </div>
            <button type="button" id="submitBtn" class="submit-btn">签署并提交核心转账</button>
        </form>
    </div>
    
    <div class="notice-card">
        <div>
            <h3>🛡️ 金融安全风控告示</h3>
            <p>1. 本接口已接入分布式账本 Session 态势感知防线，非登录会话拒绝写入任何指令。</p>
            <p>2. 交易过程处于本地 MySQL 事务隔离级别保护下，确保付款与收款对调指令的强原子性。</p>
            <p>3. 请务必仔细核对收款方借记卡号，涉及资金清算指令一经签署无法撤销。</p>
        </div>
        <a href="dashboard.html" class="back-link">🔙 返回我的财务看板</a>
    </div>
</div>

<div class="receipt-overlay" id="receiptOverlay">
    <div class="receipt-box">
        <div class="receipt-title">🎉 核心系统电子转账凭证</div>
        
        <div class="receipt-row">
            <span class="receipt-label">交易状态</span>
            <span class="receipt-value" style="color: #4CAF50; font-weight: bold;">SUCCESS (成功)</span>
        </div>
        <div class="receipt-row">
            <span class="receipt-label">付款方卡号</span>
            <span class="receipt-value" id="r_source">---</span>
        </div>
        <div class="receipt-row">
            <span class="receipt-label">收款方卡号</span>
            <span class="receipt-value" id="r_target">---</span>
        </div>
        <div class="receipt-row">
            <span class="receipt-label">结算金额</span>
            <span class="receipt-value" style="color: #f44336; font-weight: bold;" id="r_amount">￥0.00</span>
        </div>
        <div class="receipt-row">
            <span class="receipt-label">清算时间</span>
            <span class="receipt-value" id="r_time">2026-06-24</span>
        </div>

        <div class="bank-seal">
            <span>虚拟银行</span>
            <span style="font-size: 9px; border-top: 1px dashed rgba(244,67,54,0.4); margin-top:2px; padding-top:2px;">核心业务专用章</span>
        </div>

        <button type="button" class="close-receipt-btn" id="closeReceiptBtn">确认并清空</button>
    </div>
</div>

<script>
    document.getElementById('submitBtn').addEventListener('click', function() {
        // 使用调通的全新对接变量名
        const payload = {
            source_card: document.getElementById('source_account').value,
            target_card: document.getElementById('target_account').value,
            amount: document.getElementById('amount').value
        };

        const originalBtnText = this.innerText;
        this.innerText = "核心节点清算中...";
        this.disabled = true;

        fetch('transfer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            this.innerText = originalBtnText;
            this.disabled = false;

            if (data.code === 200) {
                // 转账成功，优雅地把回单数据填上
                document.getElementById('r_source').innerText = payload.source_card;
                document.getElementById('r_target').innerText = payload.target_card;
                document.getElementById('r_amount').innerText = `￥${parseFloat(payload.amount).toFixed(2)}`;
                
                const now = new Date();
                document.getElementById('r_time').innerText = now.toLocaleString();
                
                // 激活第三步样式，并弹出盖章回单
                document.getElementById('step3').classList.add('active');
                document.getElementById('receiptOverlay').style.display = 'flex';
            } else {
                alert("❌ 转账拒绝：" + (data.message || "参数错误"));
            }
        })
        .catch(err => {
            this.innerText = originalBtnText;
            this.disabled = false;
            alert("⚠️ 核心节点响应超时，请检查网络");
        });
    });

    // 点击回单确认按钮，关闭弹窗并跳回大盘
    document.getElementById('closeReceiptBtn').addEventListener('click', function() {
        document.getElementById('receiptOverlay').style.display = 'none';
        window.location.href = 'dashboard.html';
    });
</script>
</body>
</html>