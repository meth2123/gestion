<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            color: #333;
            text-align: center;
            padding: 50px 20px;
            margin: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #fff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            font-size: 36px;
            margin-bottom: 20px;
            color: #d9534f;
        }
        p {
            font-size: 18px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        a {
            display: inline-block;
            background-color: #5bc0de;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        a:hover {
            background-color: #31b0d5;
        }
    </style>
</head>
<body>
    <?php if (!isset($_SESSION['teacher_id'])) { ?>
<div class="container">
    <h1>404 Not Found</h1>
    <p>La page que vous recherchez est en cours de développement ou n'existe pas.</p>
    <a href="index.php">Retour au tableau de bord</a>
</div>
<?php } ?>
</body>
</html>
