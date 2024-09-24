<?php
require_once 'init.php';
include 'Inventory.php';
$inventory = new Inventory();

if(!empty($_GET['action']) && $_GET['action'] == 'logout') {
    // Unset all of the session variables
    $_SESSION = array();

    // Destroy the session.
    session_destroy();

    // Remove any cookies
    setSecureCookie('user_id', '', time() - 3600, '/');
    // Remove any other cookies set during login...

    header("Location: login.php");
    exit();
}

// Sanitize and validate input
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8');
}

// CSRF protection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }
}

if(!empty($_POST['action'])) {
    $action = sanitizeInput($_POST['action']);
    switch($action) {
        case 'getInventoryDetails':
            $inventory->getInventoryDetails();
            break;
        case 'customerList':
            $inventory->getCustomerList();
            break;
        case 'categoryList':
            $inventory->getCategoryList();
            break;
        case 'listBrand':
            $inventory->getBrandList();
            break;
        case 'listProduct':
            $inventory->getProductList();
            break;
        case 'supplierList':
            $inventory->getSupplierList();
            break;
        case 'listPurchase':
            $inventory->listPurchase();
            break;
        case 'listOrder':
            $inventory->listOrders();
            break;
    }
}

if(!empty($_POST['btn_action'])) {
    $btn_action = sanitizeInput($_POST['btn_action']);
    switch($btn_action) {
        case 'customerAdd':
            $inventory->saveCustomer();
            break;
        case 'getCustomer':
            $inventory->getCustomer();
            break;
        case 'customerUpdate':
            $inventory->updateCustomer();
            break;
        case 'customerDelete':
            $inventory->deleteCustomer();
            break;
        case 'categoryAdd':
            $inventory->saveCategory();
            break;
        case 'getCategory':
            $inventory->getCategory();
            break;
        case 'updateCategory':
            $inventory->updateCategory();
            break;
        case 'deleteCategory':
            $inventory->deleteCategory();
            break;
        case 'addBrand':
            $inventory->saveBrand();
            break;
        case 'getBrand':
            $inventory->getBrand();
            break;
        case 'updateBrand':
            $inventory->updateBrand();
            break;
        case 'deleteBrand':
            $inventory->deleteBrand();
            break;
        case 'getCategoryBrand':
            echo $inventory->getCategoryBrand(sanitizeInput($_POST['categoryid']));
            break;
        case 'addProduct':
            $inventory->addProduct();
            break;
        case 'getProductDetails':
            $inventory->getProductDetails();
            break;
        case 'updateProduct':
            $inventory->updateProduct();
            break;
        case 'deleteProduct':
            $inventory->deleteProduct();
            break;
        case 'viewProduct':
            $inventory->viewProductDetails();
            break;
        case 'addSupplier':
            $inventory->addSupplier();
            break;
        case 'getSupplier':
            $inventory->getSupplier();
            break;
        case 'updateSupplier':
            $inventory->updateSupplier();
            break;
        case 'deleteSupplier':
            $inventory->deleteSupplier();
            break;
        case 'addPurchase':
            $inventory->addPurchase();
            break;
        case 'getPurchaseDetails':
            $inventory->getPurchaseDetails();
            break;
        case 'updatePurchase':
            $inventory->updatePurchase();
            break;
        case 'deletePurchase':
            $inventory->deletePurchase();
            break;
        case 'addOrder':
            $inventory->addOrder();
            break;
        case 'getOrderDetails':
            $inventory->getOrderDetails();
            break;
        case 'updateOrder':
            $inventory->updateOrder();
            break;
        case 'deleteOrder':
            $inventory->deleteOrder();
            break;
    }
}

