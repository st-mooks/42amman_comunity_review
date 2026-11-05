<?php
function validate_register_input($first_name, $last_name, $username, $email, $password, $confirm_password) {
   
    $errors = [];
    //first name validation
    if (empty($first_name)) {
        $errors[] = "First name is required.";
    } elseif (!preg_match("/^[a-zA-Z]+$/", $first_name)) {
        $errors[] = "First name can only contain letters.";
    }
    //last name validation
    if (empty($last_name)) {
        $errors[] = "Last name is required.";
    } elseif (!preg_match("/^[a-zA-Z]+$/", $last_name)) {
        $errors[] = "Last name can only contain letters.";
    }
    //username validation
    if (empty($username)) {
        $errors[] = "Username is required.";
    } elseif (!preg_match("/^[a-zA-Z_]+$/", $username)) {
        $errors[] = "Username can only contain letters and underscores.";
    }
    //email validation
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    //password validation
    if (empty($password)) {
        $errors[] = "Password is required.";
    } 
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    if (strlen($password) > 128) {
    $errors[] = "Password must not exceed 128 characters.";
    }
    if (!preg_match("/[A-Z]/", $password)) {
        $errors[] = "Password must contain at least one uppercase letter.";
    } 
    if (!preg_match("/[a-z]/", $password)) {
        $errors[] = "Password must contain at least one lowercase letter.";
    }
    if (!preg_match("/[0-9]/", $password)) {
        $errors[] = "Password must contain at least one number.";
    }
    if (!preg_match("/[^a-zA-Z0-9\s]/", $password)) {
        $errors[] = "Password must contain at least one special character.";
    }
    //confirm password validation
    if (empty($confirm_password)) {
        $errors[] = "Confirm password is required.";
    } elseif ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    return $errors;
}
?>