<?php
session_start();

/* ---------- CONFIG ---------- */
$file = "data.json";
$deletePassword = "1234";

$data = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

/* ---------- SAVE ---------- */
function saveData($data, $file){
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

/* ---------- ADD INCOME ---------- */
if(isset($_POST['add_income'])){
    $cat = trim($_POST['income_category']);
    $amt = floatval($_POST['income_amount']);
    $note = trim($_POST['income_note']);

    if($cat && $amt>0){
        if(!isset($data[$cat])){
            $data[$cat] = ["balance"=>0,"entries"=>[]];
        }

        $data[$cat]["balance"] += $amt;
        $data[$cat]["entries"][] = [
            "type"=>"income",
            "amount"=>$amt,
            "note"=>$note,
            "date"=>date("Y-m-d H:i")
        ];

        saveData($data,$file);
    }
    header("Location: index.php");
    exit;
}

/* ---------- ADD EXPENSE ---------- */
if(isset($_POST['add_expense'])){
    $cat = $_POST['expense_category'];
    $amt = floatval($_POST['expense_amount']);
    $note = trim($_POST['expense_note']);

    if(isset($data[$cat]) && $amt>0){
        $data[$cat]["balance"] -= $amt;
        $data[$cat]["entries"][] = [
            "type"=>"expense",
            "amount"=>$amt,
            "note"=>$note,
            "date"=>date("Y-m-d H:i")
        ];

        saveData($data,$file);
    }
    header("Location: index.php");
    exit;
}

/* ---------- DELETE ENTRY ---------- */
if(isset($_GET['delete'])){
    $cat = $_GET['cat'];
    $i = intval($_GET['delete']);

    if(isset($data[$cat]["entries"][$i])){
        $e = $data[$cat]["entries"][$i];

        if($e["type"]=="income"){
            $data[$cat]["balance"] -= $e["amount"];
        }else{
            $data[$cat]["balance"] += $e["amount"];
        }

        array_splice($data[$cat]["entries"],$i,1);
        saveData($data,$file);
    }
    header("Location: index.php");
    exit;
}

/* ---------- EDIT ENTRY ---------- */
if(isset($_POST['update_entry'])){
    $cat = $_POST['cat'];
    $i = intval($_POST['index']);
    $amt = floatval($_POST['amount']);
    $note = trim($_POST['note']);

    if(isset($data[$cat]["entries"][$i])){
        $old = $data[$cat]["entries"][$i];

        // Reverse old balance effect
        if($old["type"]=="income"){
            $data[$cat]["balance"] -= $old["amount"];
        }else{
            $data[$cat]["balance"] += $old["amount"];
        }

        // Apply new
        if($old["type"]=="income"){
            $data[$cat]["balance"] += $amt;
        }else{
            $data[$cat]["balance"] -= $amt;
        }

        $data[$cat]["entries"][$i]["amount"] = $amt;
        $data[$cat]["entries"][$i]["note"] = $note;

        saveData($data,$file);
    }
    header("Location: index.php");
    exit;
}

/* ---------- DELETE CATEGORY ---------- */
if(isset($_GET['delete_category'])){
    unset($data[$_GET['delete_category']]);
    saveData($data,$file);
    header("Location: index.php");
    exit;
}

/* ---------- DELETE ALL EXPENSES (PASSWORD) ---------- */
if(isset($_POST['delete_all'])){
    if($_POST['password']===$deletePassword){

        foreach($data as $cat=>$d){
            $new=[];
            $bal=0;

            foreach($d["entries"] as $e){
                if($e["type"]=="income"){
                    $new[]=$e;
                    $bal += $e["amount"];
                }
            }

            $data[$cat]["entries"]=$new;
            $data[$cat]["balance"]=$bal;
        }

        saveData($data,$file);
        $_SESSION['msg']="All expenses deleted";
    }else{
        $_SESSION['msg']="Wrong password";
    }

    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>

<html>
<head>
<title>Money Manager</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body{font-family:Arial;margin:0;background:#f2f2f2;padding:10px}
.box{max-width:600px;margin:auto;background:#fff;padding:15px;border-radius:6px}
h2{margin-top:20px}
input,select,button{width:100%;padding:8px;margin:5px 0}
.entry{border-bottom:1px solid #ddd;padding:6px 0}
.income{color:green}
.expense{color:red}
.small{font-size:12px;color:#666}
a{font-size:12px}
.msg{background:#ffffcc;padding:8px;margin-bottom:10px}
</style>
</head>
<body>

<div class="box">
<h1>Money Manager</h1>

<?php
if(isset($_SESSION['msg'])){
    echo '<div class="msg">'.$_SESSION['msg'].'</div>';
    unset($_SESSION['msg']);
}
?>

<h2>Add Income</h2>
<form method="post">
<input name="income_category" placeholder="Category (Bank/Cash)" required>
<input type="number" step="0.01" name="income_amount" placeholder="Amount" required>
<input name="income_note" placeholder="Note">
<button name="add_income">Add Income</button>
</form>

<h2>Add Expense</h2>
<form method="post">
<select name="expense_category" required>
<option value="">Select Category</option>
<?php foreach($data as $cat=>$d) echo "<option>$cat</option>"; ?>
</select>
<input type="number" step="0.01" name="expense_amount" placeholder="Amount" required>
<input name="expense_note" placeholder="Note">
<button name="add_expense">Add Expense</button>
</form>

<h2>Delete All Expenses</h2>
<form method="post">
<input type="password" name="password" placeholder="Password">
<button name="delete_all">Delete All Expenses</button>
</form>

<h2>Accounts</h2>

<?php foreach($data as $cat=>$d): ?>

<h3>
<?php echo $cat ?> (Balance: <?php echo $d["balance"] ?>)
<a href="?delete_category=<?php echo $cat ?>" onclick="return confirm('Delete category?')">Delete Category</a>
</h3>

<?php foreach($d["entries"] as $i=>$e): ?>

<?php if(isset($_GET['edit']) && $_GET['edit']==$i && $_GET['cat']==$cat): ?>

<form method="post" class="entry">
<input type="hidden" name="cat" value="<?php echo $cat ?>">
<input type="hidden" name="index" value="<?php echo $i ?>">
<input type="number" step="0.01" name="amount" value="<?php echo $e['amount'] ?>" required>
<input name="note" value="<?php echo htmlspecialchars($e['note']) ?>">
<button name="update_entry">Save</button>
</form>

<?php else: ?>

<div class="entry">
<span class="<?php echo $e['type'] ?>">
<?php echo $e['type'] ?>: <?php echo $e['amount'] ?>
</span>
- <?php echo htmlspecialchars($e['note']) ?>
<div class="small">
<?php echo $e['date'] ?>
<a href="?cat=<?php echo $cat ?>&edit=<?php echo $i ?>">Edit</a>
<a href="?cat=<?php echo $cat ?>&delete=<?php echo $i ?>">Delete</a>
</div>
</div>

<?php endif; ?>

<?php endforeach; ?>

<?php endforeach; ?>

</div>
</body>
</html>
