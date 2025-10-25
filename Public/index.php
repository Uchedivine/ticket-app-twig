<?php
session_start();

require_once __DIR__ . '/../vendor/autoload.php';

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

// Initialize Twig
$loader = new FilesystemLoader(__DIR__ . '/../templates');
$twig = new Environment($loader, [
    'cache' => false, // Disable cache for development
    'debug' => true,
]);

// Get current page from URL
$page = $_GET['page'] ?? 'landing';

// Handle POST requests (form submissions)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'signup':
            handleSignup();
            break;
        case 'login':
            handleLogin();
            break;
        case 'logout':
            handleLogout();
            break;
        case 'create_ticket':
            handleCreateTicket();
            break;
        case 'update_ticket':
            handleUpdateTicket();
            break;
        case 'delete_ticket':
            handleDeleteTicket();
            break;
    }
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user']);
$user = $_SESSION['user'] ?? null;

// Handle routing
switch ($page) {
    case 'landing':
        if ($isLoggedIn) {
            header('Location: ?page=dashboard');
            exit;
        }
        echo $twig->render('landing.html.twig');
        break;
        
    case 'auth':
        if ($isLoggedIn) {
            header('Location: ?page=dashboard');
            exit;
        }
        $mode = $_GET['mode'] ?? 'login';
        $error = $_SESSION['error'] ?? null;
        unset($_SESSION['error']);
        echo $twig->render('auth.html.twig', [
            'mode' => $mode,
            'error' => $error
        ]);
        break;
        
    case 'dashboard':
        if (!$isLoggedIn) {
            header('Location: ?page=auth');
            exit;
        }
        $stats = getTicketStats();
        echo $twig->render('dashboard.html.twig', [
            'user' => $user,
            'stats' => $stats
        ]);
        break;
        
    case 'tickets':
        if (!$isLoggedIn) {
            header('Location: ?page=auth');
            exit;
        }
        $tickets = getTickets();
        $success = $_SESSION['success'] ?? null;
        $error = $_SESSION['error'] ?? null;
        unset($_SESSION['success'], $_SESSION['error']);
        echo $twig->render('tickets.html.twig', [
            'user' => $user,
            'tickets' => $tickets,
            'success' => $success,
            'error' => $error
        ]);
        break;
        
    default:
        header('Location: ?page=landing');
        exit;
}

// ============== HELPER FUNCTIONS ==============

function handleSignup() {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        $_SESSION['error'] = 'All fields are required';
        header('Location: ?page=auth&mode=signup');
        exit;
    }
    
    if ($password !== $confirmPassword) {
        $_SESSION['error'] = 'Passwords do not match';
        header('Location: ?page=auth&mode=signup');
        exit;
    }
    
    if (strlen($password) < 6) {
        $_SESSION['error'] = 'Password must be at least 6 characters';
        header('Location: ?page=auth&mode=signup');
        exit;
    }
    
    // Check if user exists
    $users = getUsers();
    foreach ($users as $user) {
        if ($user['email'] === $email) {
            $_SESSION['error'] = 'User already exists';
            header('Location: ?page=auth&mode=signup');
            exit;
        }
    }
    
    // Create user
    $users[] = [
        'name' => $name,
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT)
    ];
    saveUsers($users);
    
    // Auto login
    $_SESSION['user'] = [
        'name' => $name,
        'email' => $email
    ];
    
    header('Location: ?page=dashboard');
    exit;
}

function handleLogin() {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $_SESSION['error'] = 'All fields are required';
        header('Location: ?page=auth&mode=login');
        exit;
    }
    
    $users = getUsers();
    foreach ($users as $user) {
        if ($user['email'] === $email && password_verify($password, $user['password'])) {
            $_SESSION['user'] = [
                'name' => $user['name'],
                'email' => $user['email']
            ];
            header('Location: ?page=dashboard');
            exit;
        }
    }
    
    $_SESSION['error'] = 'Invalid email or password';
    header('Location: ?page=auth&mode=login');
    exit;
}

function handleLogout() {
    session_destroy();
    header('Location: ?page=landing');
    exit;
}

function handleCreateTicket() {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? '';
    $status = $_POST['status'] ?? 'open';
    
    if (empty($title) || empty($description) || empty($priority)) {
        $_SESSION['error'] = 'All fields are required';
        header('Location: ?page=tickets');
        exit;
    }
    
    $tickets = getTickets();
    $tickets[] = [
        'id' => uniqid(),
        'title' => $title,
        'description' => $description,
        'priority' => $priority,
        'status' => $status,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    saveTickets($tickets);
    
    $_SESSION['success'] = 'Ticket created successfully!';
    header('Location: ?page=tickets');
    exit;
}

function handleUpdateTicket() {
    $id = $_POST['ticket_id'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? '';
    $status = $_POST['status'] ?? '';
    
    if (empty($title) || empty($description) || empty($priority)) {
        $_SESSION['error'] = 'All fields are required';
        header('Location: ?page=tickets');
        exit;
    }
    
    $tickets = getTickets();
    foreach ($tickets as $key => $ticket) {
        if ($ticket['id'] === $id) {
            $tickets[$key]['title'] = $title;
            $tickets[$key]['description'] = $description;
            $tickets[$key]['priority'] = $priority;
            $tickets[$key]['status'] = $status;
            $tickets[$key]['updated_at'] = date('Y-m-d H:i:s');
            break;
        }
    }
    saveTickets($tickets);
    
    $_SESSION['success'] = 'Ticket updated successfully!';
    header('Location: ?page=tickets');
    exit;
}

function handleDeleteTicket() {
    $id = $_POST['ticket_id'] ?? '';
    
    $tickets = getTickets();
    $tickets = array_filter($tickets, function($ticket) use ($id) {
        return $ticket['id'] !== $id;
    });
    saveTickets(array_values($tickets));
    
    $_SESSION['success'] = 'Ticket deleted successfully!';
    header('Location: ?page=tickets');
    exit;
}

function getUsers() {
    $file = __DIR__ . '/../data/users.json';
    if (!file_exists($file)) {
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        file_put_contents($file, '[]');
    }
    return json_decode(file_get_contents($file), true) ?? [];
}

function saveUsers($users) {
    $file = __DIR__ . '/../data/users.json';
    file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT));
}

function getTickets() {
    $file = __DIR__ . '/../data/tickets.json';
    if (!file_exists($file)) {
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        file_put_contents($file, '[]');
    }
    return json_decode(file_get_contents($file), true) ?? [];
}

function saveTickets($tickets) {
    $file = __DIR__ . '/../data/tickets.json';
    file_put_contents($file, json_encode($tickets, JSON_PRETTY_PRINT));
}

function getTicketStats() {
    $tickets = getTickets();
    return [
        'total' => count($tickets),
        'open' => count(array_filter($tickets, fn($t) => $t['status'] === 'open')),
        'in_progress' => count(array_filter($tickets, fn($t) => $t['status'] === 'in_progress')),
        'closed' => count(array_filter($tickets, fn($t) => $t['status'] === 'closed'))
    ];
}