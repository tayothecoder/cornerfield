<?php
require_once '../config/Config.php';
require_once '../src/Utils/SessionManager.php';
require_once '../src/Config/Database.php';
require_once '../src/Models/Transaction.php';

SessionManager::start();

if (!SessionManager::get('user_logged_in') || !isset($_GET['transaction_id'])) {
    header('Location: dashboard.php');
    exit;
}

$database = DatabaseFactory::create();
$transactionModel = new Transaction($database);
$transaction_id = $_GET['transaction_id'];

// Get transaction details using your existing method
$transaction = $transactionModel->getTransactionById($transaction_id);

if (!$transaction || $transaction['user_id'] != SessionManager::get('user_id')) {
    header('Location: dashboard.php');
    exit;
}

// Create payment URL (placeholder - replace with real Cryptomus integration)
$payment_data = [
    'amount' => $transaction['amount'],
    'currency' => 'USD',
    'order_id' => $transaction['gateway_transaction_id'],
    'url_return' => Config::get('SITE_URL') . '/public/deposit-success.php',
    'url_callback' => Config::get('SITE_URL') . '/public/cryptomus-webhook.php'
];

$payment_url = 'https://pay.cryptomus.com/pay/' . base64_encode(json_encode($payment_data));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Complete Payment - Cornerfield</title>
    <link href="../assets/tabler/dist/css/tabler.min.css" rel="stylesheet">
    <style>
        :root { --tblr-primary: #f7931a; }
        .payment-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="container-xl">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card payment-card">
                        <div class="card-body text-center">
                            <div class="mb-4">
                                <span style="font-size: 4rem;">₿</span>
                            </div>
                            <h2 class="text-white mb-3">Complete Your Payment</h2>
                            <p class="text-white-75 mb-4">
                                Amount: <strong>$<?= number_format($transaction['amount'], 2) ?></strong><br>
                                Transaction ID: <strong><?= $transaction['gateway_transaction_id'] ?></strong>
                            </p>
                            <a href="<?= $payment_url ?>" class="btn btn-white btn-lg">Pay with Crypto</a>
                            <div class="mt-3">
                                <a href="deposit.php" class="text-white-50">← Back to Deposit</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>