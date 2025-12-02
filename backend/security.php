<?php
/**
 * Security utilities for Wolf Tactical
 * Provides CSRF protection, rate limiting, and input sanitization
 */

// CSRF Token Management
class CSRFProtection {
    private static $tokenName = 'csrf_token';
    
    public static function generateToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION[self::$tokenName] = $token;
        $_SESSION[self::$tokenName . '_time'] = time();
        
        return $token;
    }
    
    public static function validateToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if token exists
        if (!isset($_SESSION[self::$tokenName])) {
            return false;
        }
        
        // Check token expiration (1 hour)
        if (isset($_SESSION[self::$tokenName . '_time'])) {
            $tokenAge = time() - $_SESSION[self::$tokenName . '_time'];
            if ($tokenAge > 3600) {
                return false;
            }
        }
        
        // Validate token
        $valid = hash_equals($_SESSION[self::$tokenName], $token);
        
        // Regenerate token after validation
        if ($valid) {
            self::generateToken();
        }
        
        return $valid;
    }
    
    public static function getToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION[self::$tokenName])) {
            return self::generateToken();
        }
        
        return $_SESSION[self::$tokenName];
    }
}

// Rate Limiting
class RateLimiter {
    private static $maxAttempts = 10;
    private static $timeWindow = 60; // seconds
    
    public static function checkLimit($identifier, $maxAttempts = null, $timeWindow = null) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $max = $maxAttempts ?? self::$maxAttempts;
        $window = $timeWindow ?? self::$timeWindow;
        
        $key = 'rate_limit_' . md5($identifier);
        $now = time();
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 1, 'start' => $now];
            return true;
        }
        
        $data = $_SESSION[$key];
        $elapsed = $now - $data['start'];
        
        // Reset if time window has passed
        if ($elapsed > $window) {
            $_SESSION[$key] = ['count' => 1, 'start' => $now];
            return true;
        }
        
        // Check if limit exceeded
        if ($data['count'] >= $max) {
            return false;
        }
        
        // Increment counter
        $_SESSION[$key]['count']++;
        return true;
    }
    
    public static function getRemainingTime($identifier) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $key = 'rate_limit_' . md5($identifier);
        
        if (!isset($_SESSION[$key])) {
            return 0;
        }
        
        $data = $_SESSION[$key];
        $elapsed = time() - $data['start'];
        $remaining = self::$timeWindow - $elapsed;
        
        return max(0, $remaining);
    }
}

// Input Sanitization
class InputSanitizer {
    public static function sanitizeString($input) {
        if ($input === null) {
            return null;
        }
        
        $input = trim($input);
        $input = stripslashes($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        
        return $input;
    }
    
    public static function sanitizeEmail($email) {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }
    
    public static function sanitizeInt($input) {
        return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
    }
    
    public static function sanitizeFloat($input) {
        return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }
    
    public static function sanitizeUrl($url) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
    
    public static function sanitizeArray($array) {
        if (!is_array($array)) {
            return [];
        }
        
        return array_map(function($item) {
            if (is_array($item)) {
                return self::sanitizeArray($item);
            }
            return self::sanitizeString($item);
        }, $array);
    }
}

// Security Headers
class SecurityHeaders {
    public static function setHeaders() {
        // Prevent XSS attacks
        header("X-XSS-Protection: 1; mode=block");
        
        // Prevent clickjacking
        header("X-Frame-Options: SAMEORIGIN");
        
        // Prevent MIME type sniffing
        header("X-Content-Type-Options: nosniff");
        
        // Referrer policy
        header("Referrer-Policy: strict-origin-when-cross-origin");
        
        // Content Security Policy
        $csp = "default-src 'self'; ";
        $csp .= "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdn.quilljs.com; ";
        $csp .= "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://cdn.quilljs.com; ";
        $csp .= "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; ";
        $csp .= "img-src 'self' data: https:; ";
        $csp .= "connect-src 'self'; ";
        $csp .= "frame-src 'self' https://www.youtube.com https://youtube.com;";
        
        header("Content-Security-Policy: " . $csp);
        
        // Strict Transport Security (HTTPS only)
        // Uncomment when using HTTPS
        // header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
    }
}

// Password Hashing (for future use)
class PasswordHelper {
    public static function hash($password) {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
    
    public static function verify($password, $hash) {
        return password_verify($password, $hash);
    }
    
    public static function needsRehash($hash) {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID);
    }
}
?>
