
<?php

// =====================================
// 1. MYSQL CONNECTION
// =====================================

mysqli_report(MYSQLI_REPORT_OFF);

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

$conn->select_db("horizon_bank");


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

echo "Database and table are ready!" . PHP_EOL;


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
        $accountNumber = "",
        $customerName = "",
        $balance = 0
    ) {
        $this->conn = $conn;
        $this->accountNumber = $accountNumber;
        $this->customerName = $customerName;
        $this->balance = $balance;
    }


    // =================================
    // INSERT: CREATE ACCOUNT
    // =================================

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
        } else {
            echo "Insert failed: " . $stmt->error . PHP_EOL;
        }

        $stmt->close();
    }


    // =================================
    // SELECT: LOAD ONE ACCOUNT
    // =================================

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


    // =================================
    // SELECT: SHOW ONE ACCOUNT
    // =================================

    public function showBalance()
    {
        if (!$this->loadAccount()) {
            echo "Account not found!" . PHP_EOL;
            return;
        }

        echo PHP_EOL . "---------- ACCOUNT DETAILS ----------" . PHP_EOL;
        echo "Account Number: " . $this->accountNumber . PHP_EOL;
        echo "Customer Name: " . $this->customerName . PHP_EOL;
        echo "Balance: Rs. " . number_format($this->balance, 2) . PHP_EOL;
        echo "-------------------------------------" . PHP_EOL;
    }


    // =================================
    // SELECT: SHOW ALL RECORDS
    // =================================

    public function showAllRecords()
    {
        $sql = "SELECT id, account_number, customer_name, balance
                FROM customers
                ORDER BY id ASC";

        $result = $this->conn->query($sql);

        if (!$result) {
            echo "Select Error: " . $this->conn->error . PHP_EOL;
            return;
        }

        if ($result->num_rows === 0) {
            echo "No customer records found." . PHP_EOL;
            $result->free();
            return;
        }

        echo PHP_EOL . "============== ALL CUSTOMERS ==============" . PHP_EOL;

        while ($row = $result->fetch_assoc()) {
            echo "ID: " . $row["id"] . PHP_EOL;
            echo "Account: " . $row["account_number"] . PHP_EOL;
            echo "Name: " . $row["customer_name"] . PHP_EOL;
            echo "Balance: Rs. " . number_format((float) $row["balance"], 2) . PHP_EOL;
            echo "------------------------------------------" . PHP_EOL;
        }

        $result->free();
    }


    // =================================
    // UPDATE: CUSTOMER NAME
    // =================================

    public function updateName($newName)
    {
        $sql = "UPDATE customers
                SET customer_name = ?
                WHERE account_number = ?";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            echo "Prepare Error: " . $this->conn->error . PHP_EOL;
            return;
        }

        $stmt->bind_param(
            "ss",
            $newName,
            $this->accountNumber
        );

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo "Customer name updated successfully!" . PHP_EOL;
            } else {
                echo "No changes made, or account not found." . PHP_EOL;
            }
        } else {
            echo "Update failed: " . $stmt->error . PHP_EOL;
        }

        $stmt->close();
    }


    // =================================
    // UPDATE: DEPOSIT MONEY
    // =================================

    public function deposit($amount)
    {
        if (!is_finite($amount) || $amount <= 0) {
            echo "Amount must be greater than zero." . PHP_EOL;
            return;
        }

        $sql = "UPDATE customers
                SET balance = balance + ?
                WHERE account_number = ?
                AND balance <= 9999999999.99 - ?";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            echo "Prepare Error: " . $this->conn->error . PHP_EOL;
            return;
        }

        $stmt->bind_param(
            "dsd",
            $amount,
            $this->accountNumber,
            $amount
        );

        if ($stmt->execute() && $stmt->affected_rows === 1) {
            echo "Deposit successful!" . PHP_EOL;
        } else {
            echo "Deposit failed. Check the account and balance limit." . PHP_EOL;
        }

        $stmt->close();
    }


    // =================================
    // UPDATE: WITHDRAW MONEY
    // =================================

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
            echo "Prepare Error: " . $this->conn->error . PHP_EOL;
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
        } else {
            echo "Withdrawal failed. Insufficient balance or invalid account." . PHP_EOL;
        }

        $stmt->close();
    }


    // =================================
    // DELETE: REMOVE ACCOUNT
    // =================================

    public function deleteAccount()
    {
        $sql = "DELETE FROM customers
                WHERE account_number = ?";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            echo "Prepare Error: " . $this->conn->error . PHP_EOL;
            return;
        }

        $stmt->bind_param("s", $this->accountNumber);

        if ($stmt->execute()) {
            if ($stmt->affected_rows === 1) {
                echo "Account deleted successfully!" . PHP_EOL;
            } else {
                echo "Account not found." . PHP_EOL;
            }
        } else {
            echo "Delete failed: " . $stmt->error . PHP_EOL;
        }

        $stmt->close();
    }
}


// =====================================
// 6. TERMINAL MENU
// =====================================

$bank = new BankAccount($conn);

while (true) {

    echo PHP_EOL;
    echo "========================================" . PHP_EOL;
    echo "        HORIZON BANK MANAGEMENT         " . PHP_EOL;
    echo "========================================" . PHP_EOL;
    echo "1.  Create Account (INSERT)" . PHP_EOL;
    echo "2.  Show All Customers (SELECT)" . PHP_EOL;
    echo "3.  Search Account (SELECT)" . PHP_EOL;
    echo "4.  Update Customer Name (UPDATE)" . PHP_EOL;
    echo "5.  Deposit Money (UPDATE)" . PHP_EOL;
    echo "6.  Withdraw Money (UPDATE)" . PHP_EOL;
    echo "7.  Delete Account (DELETE)" . PHP_EOL;
    echo "8.  Exit" . PHP_EOL;
    echo "========================================" . PHP_EOL;

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
            strlen($customerName) > 100 ||
            !is_numeric($balanceInput) ||
            !is_finite((float) $balanceInput) ||
            (float) $balanceInput < 0 ||
            (float) $balanceInput > 9999999999.99
        ) {
            echo "Invalid input. Please try again." . PHP_EOL;
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


    // SHOW ALL CUSTOMERS
    elseif ($choice === "2") {
        $bank->showAllRecords();
    }


    // SEARCH ACCOUNT
    elseif ($choice === "3") {

        $accountNumber = getInput("Enter Account Number: ");

        if ($accountNumber === "") {
            echo "Account number is required." . PHP_EOL;
            continue;
        }

        $account = new BankAccount($conn, $accountNumber);
        $account->showBalance();
    }


    // UPDATE CUSTOMER NAME
    elseif ($choice === "4") {

        $accountNumber = getInput("Enter Account Number: ");
        $newName = getInput("Enter New Customer Name: ");

        if (
            $accountNumber === "" ||
            $newName === "" ||
            strlen($newName) > 100
        ) {
            echo "Invalid account number or customer name." . PHP_EOL;
            continue;
        }

        $account = new BankAccount($conn, $accountNumber);

        if (!$account->loadAccount()) {
            echo "Account not found." . PHP_EOL;
            continue;
        }

        $account->updateName($newName);
    }


    // DEPOSIT
    elseif ($choice === "5") {

        $accountNumber = getInput("Enter Account Number: ");
        $amountInput = getInput("Enter Deposit Amount: ");

        if (
            $accountNumber === "" ||
            !is_numeric($amountInput) ||
            !is_finite((float) $amountInput) ||
            (float) $amountInput <= 0 ||
            (float) $amountInput > 9999999999.99
        ) {
            echo "Invalid account number or amount." . PHP_EOL;
            continue;
        }

        $account = new BankAccount($conn, $accountNumber);

        if (!$account->loadAccount()) {
            echo "Account not found." . PHP_EOL;
            continue;
        }

        $account->deposit((float) $amountInput);
        $account->showBalance();
    }


    // WITHDRAW
    elseif ($choice === "6") {

        $accountNumber = getInput("Enter Account Number: ");
        $amountInput = getInput("Enter Withdrawal Amount: ");

        if (
            $accountNumber === "" ||
            !is_numeric($amountInput) ||
            !is_finite((float) $amountInput) ||
            (float) $amountInput <= 0 ||
            (float) $amountInput > 9999999999.99
        ) {
            echo "Invalid account number or amount." . PHP_EOL;
            continue;
        }

        $account = new BankAccount($conn, $accountNumber);

        if (!$account->loadAccount()) {
            echo "Account not found." . PHP_EOL;
            continue;
        }

        $account->withdraw((float) $amountInput);
        $account->showBalance();
    }


    // DELETE ACCOUNT
    elseif ($choice === "7") {

        $accountNumber = getInput("Enter Account Number to delete: ");

        if ($accountNumber === "") {
            echo "Account number is required." . PHP_EOL;
            continue;
        }

        $account = new BankAccount($conn, $accountNumber);

        if (!$account->loadAccount()) {
            echo "Account not found." . PHP_EOL;
            continue;
        }

        $account->showBalance();

        $confirm = getInput(
            "Permanently delete this account? Type YES to confirm: "
        );

        if ($confirm === "YES") {
            $account->deleteAccount();
        } else {
            echo "Delete cancelled." . PHP_EOL;
        }
    }


    // EXIT
    elseif ($choice === "8") {

        echo "Thank you for using Horizon Bank!" . PHP_EOL;
        break;
    }


    // INVALID CHOICE
    else {
        echo "Invalid choice! Select 1 to 8." . "\n";
    }
}


// =====================================
// 7. CLOSE CONNECTION
// =====================================

$conn->close();

?>
