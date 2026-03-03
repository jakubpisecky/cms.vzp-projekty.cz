<?php
require_once __DIR__ . "/includes/db.php";

// Smaž expirované tokeny
$conn->query("DELETE FROM user_remember_tokens WHERE expires_at < NOW()");

// (volitelně) Omez max. 5 tokenů na uživatele
$sql = "
  DELETE urt
  FROM user_remember_tokens urt
  JOIN (
    SELECT user_id, id
    FROM (
      SELECT user_id, id,
             ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY expires_at DESC) as rn
      FROM user_remember_tokens
    ) x
    WHERE rn > 5
  ) t ON urt.id = t.id
";
$conn->query($sql);
