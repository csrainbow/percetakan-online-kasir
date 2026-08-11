<?php
$db = new PDO('sqlite:/root/percetakan-online/database.sqlite');
$db->exec("UPDATE products SET price=35000 WHERE id=9");
echo "Fixed\n";
