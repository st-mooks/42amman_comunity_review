<?php

function validate_register_input(
    $full_name,
    $username,
    $email,
    $password,
    $confirm_password
) {
    $errors = [];

    // Full name validation
    if (empty($full_name)) {
        $errors[] = "Full name is required.";
    } elseif (!preg_match("/^\p{L}+([\s'-]\p{L}+)*$/u", $full_name)) {
        $errors[] = "Full name can only contain letters, spaces, hyphens and apostrophes.";
    }

    // Username validation
    if (empty($username)) {
        $errors[] = "Username is required.";
    } elseif (!preg_match("/^[a-zA-Z0-9_-]{2,30}$/", $username)) {
        $errors[] = "Username can only contain letters, numbers, - and _ (2-30 characters).";
    }

    // Email validation
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    // Password validation
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

    // Confirm password validation
    if (empty($confirm_password)) {
        $errors[] = "Confirm password is required.";
    } elseif ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    return $errors;
}
?>
