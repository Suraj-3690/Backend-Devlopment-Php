
<?php

// =====================================
// 1. MYSQL CONNECTION
// =====================================

$host = "127.0.0.1";
$username = "workbench";
$password = "Work123@456";
$port = 3306;

$conn = new mysqli($host, $username, $password, "", $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . PHP_EOL);
}

$conn->set_charset("utf8mb4");

echo "MySQL Connected Successfully!" . PHP_EOL;


// =====================================
// 2. CREATE DATABASE
// =====================================

$sql = "CREATE DATABASE IF NOT EXISTS horizon_bank";

if (!$conn->query($sql)) {
    die("Database Error: " . $conn->error . PHP_EOL);
}

echo "Database horizon_bank is ready!" . PHP_EOL;


// Select database
if (!$conn->select_db("horizon_bank")) {
    die("Database selection failed: " . $conn->error . PHP_EOL);
}


// =====================================
// 3. CREATE TABLE
// =====================================

$sql = "CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_number VARCHAR(20) NOT NULL UNIQUE,
    customer_name VARCHAR(100) NOT NULL,
    balance DECIMAL(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB";

if (!$conn->query($sql)) {
    die("Table Error: " . $conn->error . PHP_EOL);
}

echo "Customers table is ready!" . PHP_EOL;


// =====================================
// 4. INPUT FUNCTION
// =====================================

function getInput($message)
{
    echo $message;
    return trim(fgets(STDIN));
}


// =====================================
// 5. BANK ACCOUNT CLASS
// =====================================

class BankAccount
{
    private $conn;

    public $accountNumber;
    public $customerName;
    public $balance;

    public function __construct(
        $conn,
        $accountNumber,
        $customerName = "",
        $balance = 0
    ) {
        $this->conn = $conn;
        $this->accountNumber = $accountNumber;
        $this->customerName = $customerName;
        $this->balance = $balance;
    }


    // CREATE ACCOUNT
    public function createAccount()
    {
        $sql = "INSERT INTO customers
                (account_number, customer_name, balance)
                VALUES (?, ?, ?)";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            echo "Prepare Error: " . $this->conn->error . PHP_EOL;
            return;
        }

        $stmt->bind_param(
            "ssd",
            $this->accountNumber,
            $this->customerName,
            $this->balance
        );

        if ($stmt->execute()) {
            echo "Account created successfully!" . PHP_EOL;
            $this->showBalance();
        } else {
            echo "Account creation failed: "
                . $stmt->error . PHP_EOL;
        }

        $stmt->close();
    }


    // LOAD ACCOUNT
    public function loadAccount()
    {
        $sql = "SELECT customer_name, balance
                FROM customers
                WHERE account_number = ?";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            echo "Query Error: " . $this->conn->error . PHP_EOL;
            return false;
        }

        $stmt->bind_param("s", $this->accountNumber);

        if (!$stmt->execute()) {
            echo "Query Error: " . $stmt->error . PHP_EOL;
            $stmt->close();
            return false;
        }

        $stmt->bind_result($name, $balance);

        if ($stmt->fetch()) {
            $this->customerName = $name;
            $this->balance = (float) $balance;

            $stmt->close();
            return true;
        }

        $stmt->close();
        return false;
    }


    // DEPOSIT MONEY
    public function deposit($amount)
    {
        if (!is_finite($amount) || $amount <= 0) {
            echo "Amount must be greater than zero." . PHP_EOL;
            return;
        }

        $sql = "UPDATE customers
                SET balance = balance + ?
                WHERE account_number = ?";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            echo "Query Error: " . $this->conn->error . PHP_EOL;
            return;
        }

        $stmt->bind_param(
            "ds",
            $amount,
            $this->accountNumber
        );

        if ($stmt->execute() && $stmt->affected_rows === 1) {
            echo "Deposit successful!" . PHP_EOL;
            $this->showBalance();
        } else {
            echo "Deposit failed." . PHP_EOL;
        }

        $stmt->close();
    }


    // WITHDRAW MONEY
    public function withdraw($amount)
    {
        if (!is_finite($amount) || $amount <= 0) {
            echo "Amount must be greater than zero." . PHP_EOL;
            return;
        }

        $sql = "UPDATE customers
                SET balance = balance - ?
                WHERE account_number = ?
                AND balance >= ?";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            echo "Query Error: " . $this->conn->error . PHP_EOL;
            return;
        }

        $stmt->bind_param(
            "dsd",
            $amount,
            $this->accountNumber,
            $amount
        );

        if ($stmt->execute() && $stmt->affected_rows === 1) {
            echo "Withdrawal successful!" . PHP_EOL;
            $this->showBalance();
        } else {
            echo "Withdrawal failed. Insufficient balance or invalid account."
                . PHP_EOL;
        }

        $stmt->close();
    }


    // SHOW BALANCE
    public function showBalance()
    {
        if ($this->loadAccount()) {
            echo "---------------------------------" . PHP_EOL;
            echo "Account Holder: "
                . $this->customerName . PHP_EOL;

            echo "Account Number: "
                . $this->accountNumber . PHP_EOL;

            echo "Current Balance: Rs. "
                . number_format($this->balance, 2) . PHP_EOL;

            echo "---------------------------------" . PHP_EOL;
        } else {
            echo "Account not found." . PHP_EOL;
        }
    }
}


// =====================================
// 6. TERMINAL MENU
// =====================================

while (true) {

    echo PHP_EOL;
    echo "=================================" . PHP_EOL;
    echo "     BANK MANAGEMENT SYSTEM      " . PHP_EOL;
    echo "=================================" . PHP_EOL;

    echo "1. Create Account" . PHP_EOL;
    echo "2. Deposit Money" . PHP_EOL;
    echo "3. Withdraw Money" . PHP_EOL;
    echo "4. Check Balance" . PHP_EOL;
    echo "5. Exit" . PHP_EOL;

    $choice = getInput("Enter your choice: ");


    // CREATE ACCOUNT
    if ($choice === "1") {

        $accountNumber = getInput("Enter Account Number: ");
        $customerName = getInput("Enter Customer Name: ");
        $balanceInput = getInput("Enter Opening Balance: ");

        if (
            $accountNumber === "" ||
            strlen($accountNumber) > 20 ||
            $customerName === "" ||
            !is_numeric($balanceInput) ||
            !is_finite((float) $balanceInput) ||
            (float) $balanceInput < 0 ||
            (float) $balanceInput > 9999999999.99
        ) {
            echo "Invalid input! Please enter valid details."
                . PHP_EOL;
            continue;
        }

        $account = new BankAccount(
            $conn,
            $accountNumber,
            $customerName,
            (float) $balanceInput
        );

        $account->createAccount();
    }


    // DEPOSIT, WITHDRAW OR CHECK BALANCE
    elseif (
        $choice === "2" ||
        $choice === "3" ||
        $choice === "4"
    ) {

        $accountNumber = getInput("Enter Account Number: ");

        if ($accountNumber === "") {
            echo "Account number is required." . PHP_EOL;
            continue;
        }

        $account = new BankAccount($conn, $accountNumber);

        if (!$account->loadAccount()) {
            echo "Account not found!" . PHP_EOL;
            continue;
        }


        // DEPOSIT
        if ($choice === "2") {

            $amountInput = getInput("Enter Deposit Amount: ");

            if (
                !is_numeric($amountInput) ||
                !is_finite((float) $amountInput) ||
                (float) $amountInput <= 0
            ) {
                echo "Enter an amount greater than zero." . PHP_EOL;
                continue;
            }

            $account->deposit((float) $amountInput);
        }


        // WITHDRAW
        elseif ($choice === "3") {

            $amountInput = getInput("Enter Withdrawal Amount: ");

            if (
                !is_numeric($amountInput) ||
                !is_finite((float) $amountInput) ||
                (float) $amountInput <= 0
            ) {
                echo "Enter an amount greater than zero." . PHP_EOL;
                continue;
            }

            $account->withdraw((float) $amountInput);
        }


        // CHECK BALANCE
        else {
            $account->showBalance();
        }
    }


    // EXIT
    elseif ($choice === "5") {

        echo "Thank you for using Bank Management System!"
            . PHP_EOL;
        break;
    }


    // INVALID CHOICE
    else {
        echo "Invalid choice! Please select 1 to 5." . PHP_EOL;
    }
}


// =====================================
// 7. CLOSE MYSQL CONNECTION
// =====================================

$conn->close();

?>
