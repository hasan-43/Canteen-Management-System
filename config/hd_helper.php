<?php
// config/hd_helper.php

if (!function_exists('getHDProductImage')) {
    /**
     * Helper to return the exact uploaded product image from local resources.
     * Does not fetch any images from the internet (e.g. Unsplash).
     * Falls back to the local logo placeholder if no image has been uploaded by the admin.
     */
    function getHDProductImage($productName, $shopDbName, $localImageName = '') {
        $localImageName = trim($localImageName);
        
        $scriptName = $_SERVER['PHP_SELF'] ?? '';
        if (strpos($scriptName, '/customer/') !== false) {
            $basePath = '../';
        } elseif (strpos($scriptName, '/admin/') !== false) {
            $basePath = '../../';
        } else {
            $basePath = './';
        }

        // If the admin has uploaded an image, display it exactly
        if (!empty($localImageName)) {
            return $basePath . 'resources/' . ucfirst($shopDbName) . '/' . rawurlencode($localImageName);
        }

        // Fall back to a local default logo placeholder
        return $basePath . 'resources/logo.jpg';
    }
}
