
<?php

require_once 'validator/register_val.php';

$errors = validate_register_input(
    "Dana",
    "Farhan",
    "123456",
    "dana123",
    "dana@example.com",
    "Dana@12345",
    "Dana@12345"
);

if (empty($errors)) {
    echo "Validation passed!";
} else {
    print_r($errors);
}
?>