
<?php

echo "ADMIN: ";
echo password_hash("admin123", PASSWORD_DEFAULT);

echo "<br><br>";

echo "INVITADO: ";
echo password_hash("consulta123", PASSWORD_DEFAULT);

?>
