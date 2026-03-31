
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <style>
      
    </style>
</head>
<body>
    <?php $page_tite = "تسجيل الدخول"; ?>
    <center>
        <h1><?php echo $page_tite; ?></h1>
    <form action=""   method="POST">
        <label for="user_name">دخل اسم المستخدم</label>
        <input name="user_name"  type="text" name="user_name" placeholder="اكتب اسم المستخدم هنا ...">
          <br>
        <label for="password">كلمة المرور</label>
        <input type="password" name="password" placeholder="اكتب كلمة المرور هنا ...">
        <br>
        <input type="submit" value="تسجيل" class="button">
    </form>
    </center>
    <?php 
    
    if(isset($_POST['user_name'])){
    $user_name=$_POST["user_name"];
    echo $user_name;
    }

    if(isset($_POST['password'])){
    $password=$_POST["password"];
    echo $password;
    }
    //print_r($data);



?>
</body>
</html>