<?php
// Iniciar sesión para gestionar los modos
session_start();

// Configurar zona horaria a México
date_default_timezone_set('America/Mexico_City');

// Definir constantes
define('DATA_FILE', 'data/products.json');
define('CONFIG_FILE', 'data/config.json');
define('NOTIFICATIONS_FILE', 'data/notifications.json');
define('READ_MODE', 'read');
define('EDIT_MODE', 'edit');

// Asegurar que existe el directorio de datos
if (!file_exists('data')) {
    mkdir('data', 0755, true);
}

// Crear archivos de datos si no existen
if (!file_exists(DATA_FILE)) {
    file_put_contents(DATA_FILE, json_encode([]));
    chmod(DATA_FILE, 0644);
}

// Crear archivo de notificaciones si no existe
if (!file_exists(NOTIFICATIONS_FILE)) {
    file_put_contents(NOTIFICATIONS_FILE, json_encode([]));
    chmod(NOTIFICATIONS_FILE, 0644);
}

// Crear o cargar archivo de configuración
if (!file_exists(CONFIG_FILE)) {
    $defaultConfig = [
        'boxes_per_pallet' => 49,
        'colors' => [
            'low' => 'bg-gradient-to-r from-red-400 to-red-500',
            'medium' => 'bg-gradient-to-r from-yellow-400 to-yellow-500',
            'high' => 'bg-gradient-to-r from-blue-400 to-blue-500',
            'complete' => 'bg-gradient-to-r from-green-400 to-green-500'
        ],
        'thresholds' => [
            'low' => 40,
            'medium' => 70,
            'high' => 100
        ],
        'notify_on_complete' => true,
        'notification_sound' => true,
        'notify_on_close' => true,
        'max_notifications' => 50,
        'favorites' => []
    ];
    file_put_contents(CONFIG_FILE, json_encode($defaultConfig, JSON_PRETTY_PRINT));
    chmod(CONFIG_FILE, 0644);
}

// Cargar configuración
function loadConfig() {
    if (file_exists(CONFIG_FILE)) {
        $data = file_get_contents(CONFIG_FILE);
        $config = json_decode($data, true);
        
        // Asegurar que todos los valores predeterminados existan
        $defaults = [
            'boxes_per_pallet' => 49,
            'colors' => [
                'low' => 'bg-gradient-to-r from-red-400 to-red-500',
                'medium' => 'bg-gradient-to-r from-yellow-400 to-yellow-500',
                'high' => 'bg-gradient-to-r from-blue-400 to-blue-500',
                'complete' => 'bg-gradient-to-r from-green-400 to-green-500'
            ],
            'thresholds' => [
                'low' => 40,
                'medium' => 70,
                'high' => 100
            ],
            'notify_on_complete' => true,
            'notification_sound' => true,
            'notify_on_close' => true,
            'max_notifications' => 50,
            'favorites' => []
        ];
        
        // Si falta alguna configuración, usar el valor predeterminado
        if (!isset($config['boxes_per_pallet'])) $config['boxes_per_pallet'] = $defaults['boxes_per_pallet'];
        if (!isset($config['colors'])) $config['colors'] = $defaults['colors'];
        if (!isset($config['thresholds'])) $config['thresholds'] = $defaults['thresholds'];
        if (!isset($config['notify_on_complete'])) $config['notify_on_complete'] = $defaults['notify_on_complete'];
        if (!isset($config['notification_sound'])) $config['notification_sound'] = $defaults['notification_sound'];
        if (!isset($config['notify_on_close'])) $config['notify_on_close'] = $defaults['notify_on_close'];
        if (!isset($config['max_notifications'])) $config['max_notifications'] = $defaults['max_notifications'];
        if (!isset($config['favorites'])) $config['favorites'] = $defaults['favorites'];
        
        // Verificar cada color
        foreach (['low', 'medium', 'high', 'complete'] as $level) {
            if (!isset($config['colors'][$level])) {
                $config['colors'][$level] = $defaults['colors'][$level];
            }
        }
        
        // Verificar cada umbral
        foreach (['low', 'medium', 'high'] as $level) {
            if (!isset($config['thresholds'][$level])) {
                $config['thresholds'][$level] = $defaults['thresholds'][$level];
            }
        }
        
        return $config;
    }
    
    // Configuración predeterminada
    return [
        'boxes_per_pallet' => 49,
        'colors' => [
            'low' => 'bg-gradient-to-r from-red-400 to-red-500',
            'medium' => 'bg-gradient-to-r from-yellow-400 to-yellow-500',
            'high' => 'bg-gradient-to-r from-blue-400 to-blue-500',
            'complete' => 'bg-gradient-to-r from-green-400 to-green-500'
        ],
        'thresholds' => [
            'low' => 40,
            'medium' => 70,
            'high' => 100
        ],
        'notify_on_complete' => true,
        'notification_sound' => true,
        'notify_on_close' => true,
        'max_notifications' => 50,
        'favorites' => []
    ];
}

// Función para guardar configuración
function saveConfig($config) {
    file_put_contents(CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
}

// Función para cargar notificaciones
function loadNotifications() {
    if (file_exists(NOTIFICATIONS_FILE)) {
        $data = file_get_contents(NOTIFICATIONS_FILE);
        return json_decode($data, true) ?? [];
    }
    return [];
}

// Función para guardar notificaciones
function saveNotifications($notifications) {
    file_put_contents(NOTIFICATIONS_FILE, json_encode($notifications, JSON_PRETTY_PRINT));
}

// Función para añadir una notificación
function addNotification($type, $message, $productId, $productName, $shift) {
    $config = loadConfig();
    $notifications = loadNotifications();
    
    // Agregar nueva notificación
    $notification = [
        'id' => uniqid(),
        'type' => $type,
        'message' => $message,
        'productId' => $productId,
        'productName' => $productName,
        'shift' => $shift,
        'timestamp' => time(),
        'read' => false
    ];
    
    // Añadir al principio del array
    array_unshift($notifications, $notification);
    
    // Limitar el número de notificaciones
    if (count($notifications) > $config['max_notifications']) {
        $notifications = array_slice($notifications, 0, $config['max_notifications']);
    }
    
    saveNotifications($notifications);
    return $notification;
}

// Cargar configuración actual
$config = loadConfig();

// Configurar el modo (edición o lectura)
$mode = isset($_GET['mode']) ? $_GET['mode'] : READ_MODE;
if (!in_array($mode, [READ_MODE, EDIT_MODE])) {
    $mode = READ_MODE;
}
$_SESSION['mode'] = $mode;

// Manejar solicitudes AJAX
if (isset($_POST['ajax']) && $_POST['ajax'] === 'true') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'updateProduction') {
            $products = loadProducts();
            $productId = $_POST['productId'];
            $shift = $_POST['shift'];
            $newBoxes = intval($_POST['boxes']);
            $success = false;
            $message = 'Error al actualizar la producción';
            $updatedData = null;
            $completed = false;
            $closeToGoal = false;
            $notificationData = null;
            
            foreach ($products as &$product) {
                if ($product['id'] == $productId) {
                    $shiftData = $product['shifts'][$shift];
                    $updatedBoxes = $shiftData['boxes'] + $newBoxes;
                    $totalUpdatedBoxes = ($shiftData['pallets'] * $config['boxes_per_pallet']) + $updatedBoxes;
                    $calculatedPallets = calculatePallets($totalUpdatedBoxes, $config['boxes_per_pallet']);
                    
                    // Verificar si la meta se alcanzó
                    $wasComplete = $shiftData['currentProduction'] >= $shiftData['productionGoal'];
                    $isNowComplete = $totalUpdatedBoxes >= $shiftData['productionGoal'];
                    
                    // Verificar si está cerca de la meta
                    $wasCloseToGoal = isCloseToGoal($product, $shift, $config['boxes_per_pallet']);
                    
                    // Actualizar valores
                    $product['shifts'][$shift]['currentProduction'] = $totalUpdatedBoxes;
                    $product['shifts'][$shift]['pallets'] = $calculatedPallets['fullPallets'];
                    $product['shifts'][$shift]['boxes'] = $calculatedPallets['remainingBoxes'];
                    $product['last_update'] = time();
                    
                    // Verificar nuevamente si ahora está cerca de la meta
                    $isNowCloseToGoal = isCloseToGoal($product, $shift, $config['boxes_per_pallet']);
                    
                    // Calcular progreso para devolver
                    $progress = calculateProgress($totalUpdatedBoxes, $shiftData['productionGoal']);
                    $progressColor = getProgressColor($progress, $config);
                    
                    // Calcular tarimas y cajas faltantes
                    $remainingBoxes = max(0, $shiftData['productionGoal'] - $totalUpdatedBoxes);
                    $remainingData = calculatePallets($remainingBoxes, $config['boxes_per_pallet']);
                    
                    // Si acaba de completarse, marcar como completado y crear notificación
                    if (!$wasComplete && $isNowComplete) {
                        $completed = true;
                        $message = '¡META ALCANZADA! +' . $newBoxes . ' cajas agregadas';
                        
                        if ($config['notify_on_complete']) {
                            $notificationData = addNotification(
                                'success',
                                '¡META ALCANZADA!',
                                $productId,
                                $product['name'],
                                $shift
                            );
                        }
                    } 
                    // Si ahora está cerca de la meta y antes no lo estaba, crear notificación
                    else if (!$wasCloseToGoal && $isNowCloseToGoal && !$isNowComplete) {
                        $closeToGoal = true;
                        $message = '¡Faltan 3 tarimas o menos! +' . $newBoxes . ' cajas agregadas';
                        
                        if ($config['notify_on_close']) {
                            $notificationData = addNotification(
                                'warning',
                                'Faltan 3 tarimas o menos para la meta',
                                $productId,
                                $product['name'],
                                $shift
                            );
                        }
                    } 
                    else {
                        $message = '+' . $newBoxes . ' cajas agregadas';
                    }
                    
                    $updatedData = [
                        'totalBoxes' => $totalUpdatedBoxes,
                        'pallets' => $calculatedPallets['fullPallets'],
                        'boxes' => $calculatedPallets['remainingBoxes'],
                        'lastUpdate' => date('d/m/Y H:i', $product['last_update']),
                        'progress' => $progress,
                        'progressColor' => $progressColor,
                        'completed' => $completed,
                        'closeToGoal' => $closeToGoal,
                        'productName' => $product['name'],
                        'remainingPallets' => $remainingData['fullPallets'],
                        'remainingBoxes' => $remainingData['remainingBoxes'],
                        'remainingTotal' => $remainingBoxes,
                        'isFavorite' => in_array($productId, $config['favorites'])
                    ];
                    
                    $success = true;
                    break;
                }
            }
            
            if ($success) {
                // Recalcular el resumen después de actualizar
                $updatedSummary = getProductionSummary($products, $config['boxes_per_pallet']);
                
                saveProducts($products);
                echo json_encode([
                    'success' => true, 
                    'message' => $message, 
                    'data' => $updatedData,
                    'notification' => $notificationData,
                    'summary' => [
                        'morning_progress' => $updatedSummary['morning_progress'],
                        'morning_pallets_produced' => $updatedSummary['morning_pallets_produced'],
                        'morning_pallets_remaining' => $updatedSummary['morning_pallets_remaining'],
                        
                        'afternoon_progress' => $updatedSummary['afternoon_progress'],
                        'afternoon_pallets_produced' => $updatedSummary['afternoon_pallets_produced'],
                        'afternoon_pallets_remaining' => $updatedSummary['afternoon_pallets_remaining'],
                        
                        'night_progress' => $updatedSummary['night_progress'],
                        'night_pallets_produced' => $updatedSummary['night_pallets_produced'],
                        'night_pallets_remaining' => $updatedSummary['night_pallets_remaining']
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => $message]);
            }
            exit;
        }
        
        if ($action === 'setExactProduction') {
            $products = loadProducts();
            $productId = $_POST['productId'];
            $shift = $_POST['shift'];
            $exactBoxes = intval($_POST['exactBoxes']);
            $success = false;
            $message = 'Error al actualizar la producción';
            $updatedData = null;
            $completed = false;
            $closeToGoal = false;
            $notificationData = null;
            
            if ($exactBoxes < 0) {
                echo json_encode(['success' => false, 'message' => 'El valor no puede ser negativo']);
                exit;
            }
            
            foreach ($products as &$product) {
                if ($product['id'] == $productId) {
                    // Verificar si la meta se alcanzó
                    $wasComplete = $product['shifts'][$shift]['currentProduction'] >= $product['shifts'][$shift]['productionGoal'];
                    $isNowComplete = $exactBoxes >= $product['shifts'][$shift]['productionGoal'];
                    
                    // Verificar si estaba cerca de la meta
                    $wasCloseToGoal = isCloseToGoal($product, $shift, $config['boxes_per_pallet']);
                    
                    $calculatedPallets = calculatePallets($exactBoxes, $config['boxes_per_pallet']);
                    
                    // Actualizar valores
                    $product['shifts'][$shift]['currentProduction'] = $exactBoxes;
                    $product['shifts'][$shift]['pallets'] = $calculatedPallets['fullPallets'];
                    $product['shifts'][$shift]['boxes'] = $calculatedPallets['remainingBoxes'];
                    $product['last_update'] = time();
                    
                    // Verificar nuevamente si ahora está cerca de la meta
                    $isNowCloseToGoal = isCloseToGoal($product, $shift, $config['boxes_per_pallet']);
                    
                    // Calcular progreso para devolver
                    $progress = calculateProgress($exactBoxes, $product['shifts'][$shift]['productionGoal']);
                    $progressColor = getProgressColor($progress, $config);
                    
                    // Calcular tarimas y cajas faltantes
                    $remainingBoxes = max(0, $product['shifts'][$shift]['productionGoal'] - $exactBoxes);
                    $remainingData = calculatePallets($remainingBoxes, $config['boxes_per_pallet']);
                    
                    // Si acaba de completarse, marcar como completado y crear notificación
                    if (!$wasComplete && $isNowComplete) {
                        $completed = true;
                        $message = '¡META ALCANZADA! Producción actualizada a ' . $exactBoxes . ' cajas';
                        
                        if ($config['notify_on_complete']) {
                            $notificationData = addNotification(
                                'success',
                                '¡META ALCANZADA!',
                                $productId,
                                $product['name'],
                                $shift
                            );
                        }
                    } 
                    // Si ahora está cerca de la meta y antes no lo estaba, crear notificación
                    else if (!$wasCloseToGoal && $isNowCloseToGoal && !$isNowComplete) {
                        $closeToGoal = true;
                        $message = '¡Faltan 3 tarimas o menos! Producción actualizada a ' . $exactBoxes . ' cajas';
                        
                        if ($config['notify_on_close']) {
                            $notificationData = addNotification(
                                'warning',
                                'Faltan 3 tarimas o menos para la meta',
                                $productId,
                                $product['name'],
                                $shift
                            );
                        }
                    } 
                    else {
                        $message = 'Producción actualizada a ' . $exactBoxes . ' cajas';
                    }
                    
                    $updatedData = [
                        'totalBoxes' => $exactBoxes,
                        'pallets' => $calculatedPallets['fullPallets'],
                        'boxes' => $calculatedPallets['remainingBoxes'],
                        'lastUpdate' => date('d/m/Y H:i', $product['last_update']),
                        'progress' => $progress,
                        'progressColor' => $progressColor,
                        'completed' => $completed,
                        'closeToGoal' => $closeToGoal,
                        'productName' => $product['name'],
                        'remainingPallets' => $remainingData['fullPallets'],
                        'remainingBoxes' => $remainingData['remainingBoxes'],
                        'remainingTotal' => $remainingBoxes,
                        'isFavorite' => in_array($productId, $config['favorites'])
                    ];
                    
                    $success = true;
                    break;
                }
            }
            
            if ($success) {
                // Recalcular el resumen después de actualizar
                $updatedSummary = getProductionSummary($products, $config['boxes_per_pallet']);
                
                saveProducts($products);
                echo json_encode([
                    'success' => true, 
                    'message' => $message, 
                    'data' => $updatedData,
                    'notification' => $notificationData,
                    'summary' => [
                        'morning_progress' => $updatedSummary['morning_progress'],
                        'morning_pallets_produced' => $updatedSummary['morning_pallets_produced'],
                        'morning_pallets_remaining' => $updatedSummary['morning_pallets_remaining'],
                        
                        'afternoon_progress' => $updatedSummary['afternoon_progress'],
                        'afternoon_pallets_produced' => $updatedSummary['afternoon_pallets_produced'],
                        'afternoon_pallets_remaining' => $updatedSummary['afternoon_pallets_remaining'],
                        
                        'night_progress' => $updatedSummary['night_progress'],
                        'night_pallets_produced' => $updatedSummary['night_pallets_produced'],
                        'night_pallets_remaining' => $updatedSummary['night_pallets_remaining']
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => $message]);
            }
            exit;
        }
        
        // Marcar notificación como leída
        if ($action === 'markNotificationRead') {
            $notificationId = $_POST['notificationId'];
            $notifications = loadNotifications();
            
            foreach ($notifications as &$notification) {
                if ($notification['id'] == $notificationId) {
                    $notification['read'] = true;
                    break;
                }
            }
            
            saveNotifications($notifications);
            echo json_encode(['success' => true]);
            exit;
        }
        
        // Marcar todas las notificaciones como leídas
        if ($action === 'markAllNotificationsRead') {
            $notifications = loadNotifications();
            
            foreach ($notifications as &$notification) {
                $notification['read'] = true;
            }
            
            saveNotifications($notifications);
            echo json_encode(['success' => true]);
            exit;
        }
        
        // Borrar todas las notificaciones
        if ($action === 'clearAllNotifications') {
            saveNotifications([]);
            echo json_encode(['success' => true]);
            exit;
        }
        
        // Alternar estado de favorito
        if ($action === 'toggleFavorite') {
            $productId = $_POST['productId'];
            $config = loadConfig();
            
            if (in_array($productId, $config['favorites'])) {
                // Eliminar de favoritos
                $config['favorites'] = array_values(array_filter($config['favorites'], function($id) use ($productId) {
                    return $id != $productId;
                }));
                $isFavorite = false;
            } else {
                // Añadir a favoritos
                $config['favorites'][] = $productId;
                $isFavorite = true;
            }
            
            saveConfig($config);
            echo json_encode(['success' => true, 'isFavorite' => $isFavorite]);
            exit;
        }
        
        // Nuevo endpoint para verificar actualizaciones
        if ($action === 'checkUpdates') {
            $lastUpdate = isset($_POST['lastUpdate']) ? intval($_POST['lastUpdate']) : 0;
            $currentShift = isset($_POST['shift']) ? $_POST['shift'] : 'all';
            $productIds = isset($_POST['productIds']) ? json_decode($_POST['productIds'], true) : [];
            $currentMode = isset($_POST['mode']) ? $_POST['mode'] : READ_MODE;
            $forceUpdateSummary = isset($_POST['forceUpdateSummary']) && $_POST['forceUpdateSummary'] === 'true';

            $products = loadProducts();
            $updatedProducts = [];
            $hasUpdates = false;

            foreach ($products as $product) {
                // Si hay IDs específicos para verificar, filtrar solo esos
                if (!empty($productIds) && !in_array($product['id'], $productIds)) {
                    continue;
                }

                // Verificar si el producto ha sido actualizado desde la última comprobación
                if (isset($product['last_update']) && $product['last_update'] > $lastUpdate) {
                    $hasUpdates = true;
                    
                    // Determinar qué turnos devolver (para cada producto pueden ser diferentes)
                    $shifts = [];
                    
                    if ($currentShift === 'all') {
                        // Si el filtro es "todos", incluir todos los turnos activos
                        if ($product['shifts']['morning']['active']) $shifts[] = 'morning';
                        if ($product['shifts']['afternoon']['active']) $shifts[] = 'afternoon';
                        if ($product['shifts']['night']['active']) $shifts[] = 'night';
                    } else {
                        // Si es un turno específico, verificar si está activo
                        if ($product['shifts'][$currentShift]['active']) $shifts[] = $currentShift;
                    }
                    
                    // Si no hay turnos activos para este producto, continuar con el siguiente
                    if (empty($shifts)) continue;
                    
                    // Para cada turno activo, preparar los datos
                    foreach ($shifts as $shift) {
                        $shiftData = $product['shifts'][$shift];
                        $totalBoxes = ($shiftData['pallets'] * $config['boxes_per_pallet']) + $shiftData['boxes'];
                        $progress = calculateProgress($totalBoxes, $shiftData['productionGoal']);
                        $progressColor = getProgressColor($progress, $config);
                        $isComplete = $totalBoxes >= $shiftData['productionGoal'];
                        $closeToGoal = isCloseToGoal($product, $shift, $config['boxes_per_pallet']);
                        $isFavorite = in_array($product['id'], $config['favorites']);
                        $continues = continuesNextShift($product, $shift);
                        
                        // Calcular tarimas y cajas faltantes
                        $remainingBoxes = max(0, $shiftData['productionGoal'] - $totalBoxes);
                        $remainingData = calculatePallets($remainingBoxes, $config['boxes_per_pallet']);
                        
                        // Crear un ID único para este producto y turno
                        $uniqueId = $product['id'] . '-' . $shift;
                        
                        $updatedProducts[$uniqueId] = [
                            'id' => $product['id'],
                            'name' => $product['name'],
                            'shift' => $shift,
                            'totalBoxes' => $totalBoxes,
                            'pallets' => $shiftData['pallets'],
                            'boxes' => $shiftData['boxes'],
                            'lastUpdate' => date('d/m/Y H:i', $product['last_update']),
                            'progress' => $progress,
                            'progressColor' => $progressColor,
                            'completed' => $isComplete,
                            'closeToGoal' => $closeToGoal,
                            'remainingPallets' => $remainingData['fullPallets'],
                            'remainingBoxes' => $remainingData['remainingBoxes'],
                            'remainingTotal' => $remainingBoxes,
                            'isFavorite' => $isFavorite,
                            'productionGoal' => $shiftData['productionGoal'],
                            'continues' => $continues
                        ];
                    }
                }
            }
            
            // Obtener el resumen SIEMPRE, incluso si no hay actualizaciones
            // Esta es la clave para garantizar que el resumen esté actualizado
            $updatedSummary = getProductionSummary($products, $config['boxes_per_pallet']);
            
            echo json_encode([
                'success' => true,
                'hasUpdates' => $hasUpdates,
                'updatedProducts' => $updatedProducts,
                'summary' => $updatedSummary,
                'serverTime' => time(),
                'mode' => $currentMode
            ]);
            exit;
        }
    }
    
    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    exit;
}

// Función para cargar los productos
function loadProducts() {
    if (file_exists(DATA_FILE)) {
        $data = file_get_contents(DATA_FILE);
        return json_decode($data, true) ?? [];
    }
    return [];
}

// Función para guardar los productos
function saveProducts($products) {
    file_put_contents(DATA_FILE, json_encode($products, JSON_PRETTY_PRINT));
}

// Función para calcular tarimas y cajas
function calculatePallets($boxes, $boxesPerPallet) {
    $fullPallets = floor($boxes / $boxesPerPallet);
    $remainingBoxes = $boxes % $boxesPerPallet;
    return ['fullPallets' => $fullPallets, 'remainingBoxes' => $remainingBoxes];
}

// Función para calcular el progreso
function calculateProgress($current, $goal) {
    if ($goal == 0) return 0; // Prevenir división por cero
    return min(round(($current / $goal) * 100), 100);
}

// Función para obtener el color del progreso
function getProgressColor($progress, $config) {
    $thresholds = $config['thresholds'];
    $colors = $config['colors'];
    
    if ($progress < $thresholds['low']) return $colors['low'];
    if ($progress < $thresholds['medium']) return $colors['medium'];
    if ($progress < $thresholds['high']) return $colors['high'];
    return $colors['complete'];
}

// Función para verificar si un producto continúa en el siguiente turno
function continuesNextShift($product, $currentShift) {
    if ($currentShift === 'morning') return $product['shifts']['afternoon']['active'];
    if ($currentShift === 'afternoon') return $product['shifts']['night']['active'];
    if ($currentShift === 'night') return $product['shifts']['morning']['active'];
    return false;
}

// Función para verificar si está cerca de la meta (3 tarimas o menos)
function isCloseToGoal($product, $shift, $boxesPerPallet) {
    $shiftData = $product['shifts'][$shift];
    if (!$shiftData['active']) return false;
    
    $totalBoxes = ($shiftData['pallets'] * $boxesPerPallet) + $shiftData['boxes'];
    $remainingBoxes = $shiftData['productionGoal'] - $totalBoxes;
    $remainingPallets = ceil($remainingBoxes / $boxesPerPallet);
    return $remainingPallets > 0 && $remainingPallets <= 3;
}

// Función para verificar si un producto ha completado su meta
function isCompleted($product, $shift) {
    $shiftData = $product['shifts'][$shift];
    if (!$shiftData['active']) return false;
    
    return $shiftData['currentProduction'] >= $shiftData['productionGoal'];
}

// Función para calcular el resumen de producción
function getProductionSummary($products, $boxesPerPallet) {
    $summary = [
        'total_products' => 0,
        'total_active' => 0,
        'total_completed' => 0,
        'total_close_to_goal' => 0,
        'total_pallets_produced' => 0,
        'total_boxes_produced' => 0,
        'total_pallets_remaining' => 0,
        'total_boxes_remaining' => 0,
        'overall_progress' => 0,
        'morning_products' => 0,
        'afternoon_products' => 0,
        'night_products' => 0,
        // Nuevas variables específicas por turno
        'morning_progress' => 0,
        'afternoon_progress' => 0,
        'night_progress' => 0,
        'morning_pallets_produced' => 0,
        'afternoon_pallets_produced' => 0,
        'night_pallets_produced' => 0,
        'morning_pallets_remaining' => 0,
        'afternoon_pallets_remaining' => 0,
        'night_pallets_remaining' => 0,
        'morning_goal' => 0,
        'afternoon_goal' => 0,
        'night_goal' => 0,
        'morning_production' => 0,
        'afternoon_production' => 0,
        'night_production' => 0
    ];
    
    $totalGoal = 0;
    $totalProduction = 0;

    // Variables para cálculos específicos por turno
    $shiftGoals = [
        'morning' => 0,
        'afternoon' => 0,
        'night' => 0
    ];
    
    $shiftProduction = [
        'morning' => 0,
        'afternoon' => 0,
        'night' => 0
    ];
    
    foreach ($products as $product) {
        $summary['total_products']++;
        
        // Contar productos por turno
        if ($product['shifts']['morning']['active']) $summary['morning_products']++;
        if ($product['shifts']['afternoon']['active']) $summary['afternoon_products']++;
        if ($product['shifts']['night']['active']) $summary['night_products']++;
        
        foreach (['morning', 'afternoon', 'night'] as $shift) {
            if (!$product['shifts'][$shift]['active']) continue;
            
            $summary['total_active']++;
            
            $shiftData = $product['shifts'][$shift];
            $totalBoxes = ($shiftData['pallets'] * $boxesPerPallet) + $shiftData['boxes'];
            $totalGoal += $shiftData['productionGoal'];
            $totalProduction += $totalBoxes;
            
            // Agregar a totales específicos por turno
            $shiftGoals[$shift] += $shiftData['productionGoal'];
            $shiftProduction[$shift] += $totalBoxes;
            
            $summary['total_pallets_produced'] += $shiftData['pallets'];
            $summary['total_boxes_produced'] += $shiftData['boxes'];
            
            // Agregar a tarimas producidas específicas por turno
            $summary[$shift . '_pallets_produced'] += $shiftData['pallets'];
            
            if (isCompleted($product, $shift)) {
                $summary['total_completed']++;
            }
            
            if (isCloseToGoal($product, $shift, $boxesPerPallet)) {
                $summary['total_close_to_goal']++;
            }
            
            // Calcular cajas y tarimas restantes
            $remainingBoxes = max(0, $shiftData['productionGoal'] - $totalBoxes);
            $remainingData = calculatePallets($remainingBoxes, $boxesPerPallet);
            
            $summary['total_pallets_remaining'] += $remainingData['fullPallets'];
            $summary['total_boxes_remaining'] += $remainingData['remainingBoxes'];
            
            // Agregar a tarimas restantes específicas por turno
            $summary[$shift . '_pallets_remaining'] += $remainingData['fullPallets'];
        }
    }
    
    // Calcular progreso general
    if ($totalGoal > 0) {
        $summary['overall_progress'] = round(($totalProduction / $totalGoal) * 100);
    }
    
    // Calcular progreso para cada turno
    foreach (['morning', 'afternoon', 'night'] as $shift) {
        $summary[$shift . '_goal'] = $shiftGoals[$shift];
        $summary[$shift . '_production'] = $shiftProduction[$shift];
        
        if ($shiftGoals[$shift] > 0) {
            $summary[$shift . '_progress'] = round(($shiftProduction[$shift] / $shiftGoals[$shift]) * 100);
        } else {
            $summary[$shift . '_progress'] = 0;
        }
    }
    
    return $summary;
}

// Función para generar URL de redirección con parámetros
function generateRedirectUrl($baseParams = []) {
    $params = [];

    // Mantener el modo actual
    $params['mode'] = isset($baseParams['mode']) ? $baseParams['mode'] : $_SESSION['mode'];
    
    // Mantener el turno seleccionado
    if (isset($baseParams['shift'])) {
        $params['shift'] = $baseParams['shift'];
    } elseif (isset($_POST['current_shift'])) {
        $params['shift'] = $_POST['current_shift'];
    } elseif (isset($_GET['shift'])) {
        $params['shift'] = $_GET['shift'];
    }
    
    // Mantener el término de búsqueda
    if (isset($baseParams['search'])) {
        $params['search'] = $baseParams['search'];
    } elseif (isset($_POST['current_search'])) {
        $params['search'] = $_POST['current_search'];
    } elseif (isset($_GET['search'])) {
        $params['search'] = $_GET['search'];
    }
    
    // Mantener el filtro
    if (isset($baseParams['filter'])) {
        $params['filter'] = $baseParams['filter'];
    } elseif (isset($_POST['current_filter'])) {
        $params['filter'] = $_POST['current_filter'];
    } elseif (isset($_GET['filter'])) {
        $params['filter'] = $_GET['filter'];
    }
    
    // Construir la URL
    return 'index.php?' . http_build_query($params);
}

// Procesar acciones en modo edición
if ($mode === EDIT_MODE) {
    
    // Agregar o editar producto
    if (isset($_POST['action']) && $_POST['action'] === 'saveProduct') {
        $products = loadProducts();
        $productData = json_decode($_POST['productData'], true);
        $isNewProduct = !isset($productData['id']);
        
        // Validar datos mínimos
        if (empty($productData['name'])) {
            $_SESSION['error'] = 'Por favor ingrese el nombre del producto';
            header('Location: ' . generateRedirectUrl());
            exit;
        }
        
        // Verificar que al menos un turno esté activo
        $hasActiveShift = false;
        foreach ($productData['shifts'] as $shift) {
            if ($shift['active'] && $shift['productionGoal'] > 0) {
                $hasActiveShift = true;
                break;
            }
        }
        
        if (!$hasActiveShift) {
            $_SESSION['error'] = 'Por favor active al menos un turno y establezca una meta de producción mayor a 0';
            header('Location: ' . generateRedirectUrl());
            exit;
        }
        
        // Si estamos editando un producto existente
        if (isset($productData['id']) && $productData['id']) {
            foreach ($products as &$product) {
                if ($product['id'] == $productData['id']) {
                    $product = $productData;
                    $product['last_update'] = time();
                    break;
                }
            }
        } else {
            // Crear nuevo producto
            $productData['id'] = time();
            $productData['created_at'] = time();
            $productData['last_update'] = time();
            $products[] = $productData;
            
            // Crear notificación de nuevo producto
            addNotification(
                'info',
                'Nuevo producto registrado',
                $productData['id'],
                $productData['name'],
                'all'
            );
        }
        
        saveProducts($products);
        header('Location: ' . generateRedirectUrl());
        exit;
    }
    
    // Eliminar producto
    if (isset($_POST['action']) && $_POST['action'] === 'deleteProduct') {
        $products = loadProducts();
        $productId = $_POST['productId'];
        $productName = '';
        
        // Obtener el nombre del producto antes de eliminarlo
        foreach ($products as $product) {
            if ($product['id'] == $productId) {
                $productName = $product['name'];
                break;
            }
        }
        
        $products = array_filter($products, function($product) use ($productId) {
            return $product['id'] != $productId;
        });
        
        // Crear notificación de producto eliminado
        if (!empty($productName)) {
            addNotification(
                'error',
                'Producto eliminado',
                $productId,
                $productName,
                'all'
            );
        }
        
        saveProducts(array_values($products));
        header('Location: ' . generateRedirectUrl());
        exit;
    }
}

// Filtrado de productos
$products = loadProducts();
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$shiftFilter = isset($_GET['shift']) ? $_GET['shift'] : 'all';
$statusFilter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$filteredProducts = [];
foreach ($products as $product) {
    // Aplicar filtro de turno
    $matchesShift = $shiftFilter === 'all' || 
        ($shiftFilter === 'morning' && $product['shifts']['morning']['active']) ||
        ($shiftFilter === 'afternoon' && $product['shifts']['afternoon']['active']) ||
        ($shiftFilter === 'night' && $product['shifts']['night']['active']);
    
    // Aplicar filtro de búsqueda
    $matchesSearch = $searchTerm === '' || stripos($product['name'], $searchTerm) !== false;
    
    // Aplicar filtro de estado
    $currentShift = $shiftFilter !== 'all' ? $shiftFilter : 
                   ($product['shifts']['morning']['active'] ? 'morning' : 
                   ($product['shifts']['afternoon']['active'] ? 'afternoon' : 
                   ($product['shifts']['night']['active'] ? 'night' : 'morning')));
    
    $isComplete = isCompleted($product, $currentShift);
    $isCloseToGoal = isCloseToGoal($product, $currentShift, $config['boxes_per_pallet']);
    $isFavorite = in_array($product['id'], $config['favorites']);
    
    $matchesStatus = $statusFilter === 'all' || 
                    ($statusFilter === 'completed' && $isComplete) ||
                    ($statusFilter === 'close' && $isCloseToGoal && !$isComplete) ||
                    ($statusFilter === 'pending' && !$isComplete && !$isCloseToGoal) ||
                    ($statusFilter === 'favorites' && $isFavorite);
    
    if ($matchesShift && $matchesSearch && $matchesStatus) {
        $filteredProducts[] = $product;
    }
}

// Ordenar productos (favoritos primero, luego por estado y finalmente por última actualización)
usort($filteredProducts, function($a, $b) use ($shiftFilter, $config) {
    // Determinar el turno activo para cada producto
    $getActiveShift = function($product) use ($shiftFilter) {
        if ($shiftFilter !== 'all') return $shiftFilter;
        if ($product['shifts']['morning']['active']) return 'morning';
        if ($product['shifts']['afternoon']['active']) return 'afternoon';
        if ($product['shifts']['night']['active']) return 'night';
        return 'morning'; // Default
    };
    
    $aShift = $getActiveShift($a);
    $bShift = $getActiveShift($b);
    
    // Verificar si alguno es favorito
    $aIsFavorite = in_array($a['id'], $config['favorites']);
    $bIsFavorite = in_array($b['id'], $config['favorites']);
    
    // Primero ordenar por favoritos
    if ($aIsFavorite !== $bIsFavorite) {
        return $aIsFavorite ? -1 : 1; // Favoritos primero
    }
    
    // Verificar si alguno de los productos ha completado su meta
    $aCompleted = isCompleted($a, $aShift);
    $bCompleted = isCompleted($b, $bShift);
    
    // Luego ordenar por estado de compleción (no completados primero)
    if ($aCompleted !== $bCompleted) {
        return $aCompleted ? 1 : -1; // Productos completados van al final
    }
    
    // Si ambos están en el mismo estado, ordenar por última actualización
    $aTime = isset($a['last_update']) ? $a['last_update'] : 0;
    $bTime = isset($b['last_update']) ? $b['last_update'] : 0;
    return $bTime - $aTime; // Más recientes primero
});

// Calcular resumen de producción
$productionSummary = getProductionSummary($products, $config['boxes_per_pallet']);

// Generar enlaces para los modos
$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$baseUrl = strtok($currentUrl, '?');

// Función para generar URL con parámetros
function generateUrl($baseUrl, $params) {
    $query = http_build_query($params);
    return $baseUrl . '?' . $query;
}

// Generar URLs para modos y turnos
$params = [];

// Preservar el término de búsqueda
if (!empty($searchTerm)) {
    $params['search'] = $searchTerm;
}

// Preservar el filtro de estado
if (!empty($statusFilter)) {
    $params['filter'] = $statusFilter;
}

// URLs para modo lectura/edición
$readParams = $params;
$readParams['mode'] = READ_MODE;
if (!empty($shiftFilter)) $readParams['shift'] = $shiftFilter;
$readModeUrl = generateUrl($baseUrl, $readParams);

$editParams = $params;
$editParams['mode'] = EDIT_MODE;
if (!empty($shiftFilter)) $editParams['shift'] = $shiftFilter;
$editModeUrl = generateUrl($baseUrl, $editParams);

// URLs para selección rápida de turnos
$allShiftParams = $params;
$allShiftParams['mode'] = $mode;
$allShiftParams['shift'] = 'all';
$allShiftUrl = generateUrl($baseUrl, $allShiftParams);

$morningParams = $params;
$morningParams['mode'] = $mode;
$morningParams['shift'] = 'morning';
$morningShiftUrl = generateUrl($baseUrl, $morningParams);

$afternoonParams = $params;
$afternoonParams['mode'] = $mode;
$afternoonParams['shift'] = 'afternoon';
$afternoonShiftUrl = generateUrl($baseUrl, $afternoonParams);

$nightParams = $params;
$nightParams['mode'] = $mode;
$nightParams['shift'] = 'night';
$nightShiftUrl = generateUrl($baseUrl, $nightParams);

// URLs para filtros de estado
$allStatusParams = $params;
$allStatusParams['mode'] = $mode;
$allStatusParams['filter'] = 'all';
if (!empty($shiftFilter)) $allStatusParams['shift'] = $shiftFilter;
$allStatusUrl = generateUrl($baseUrl, $allStatusParams);

$completedParams = $params;
$completedParams['mode'] = $mode;
$completedParams['filter'] = 'completed';
if (!empty($shiftFilter)) $completedParams['shift'] = $shiftFilter;
$completedStatusUrl = generateUrl($baseUrl, $completedParams);

$closeParams = $params;
$closeParams['mode'] = $mode;
$closeParams['filter'] = 'close';
if (!empty($shiftFilter)) $closeParams['shift'] = $shiftFilter;
$closeStatusUrl = generateUrl($baseUrl, $closeParams);

$pendingParams = $params;
$pendingParams['mode'] = $mode;
$pendingParams['filter'] = 'pending';
if (!empty($shiftFilter)) $pendingParams['shift'] = $shiftFilter;
$pendingStatusUrl = generateUrl($baseUrl, $pendingParams);

$favoritesParams = $params;
$favoritesParams['mode'] = $mode;
$favoritesParams['filter'] = 'favorites';
if (!empty($shiftFilter)) $favoritesParams['shift'] = $shiftFilter;
$favoritesStatusUrl = generateUrl($baseUrl, $favoritesParams);

// Obtener notificaciones
$notifications = loadNotifications();
$unreadCount = 0;

foreach ($notifications as $notification) {
    if (!$notification['read']) {
        $unreadCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoreo de Producción en Planta</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #f0f4f8;
            color: #1e293b;
        }
        
        .bg-card {
            background-color: #ffffff;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            border-radius: 1rem;
        }
        
        .card-hover {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            font-size: 10px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .pulse-animation {
            animation: pulse 1.5s infinite;
        }
        
        .notification-panel {
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .shift-selector {
            transition: all 0.3s ease;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }
        
        .shift-selector::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background-color: currentColor;
            transition: width 0.3s ease;
        }
        
        .shift-selector:hover {
            transform: translateY(-2px);
        }
        
        .shift-selector:hover::after {
            width: 100%;
        }
        
        .shift-selector.active {
            transform: translateY(-3px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        /* Tooltip personalizado */
        .tooltip {
            position: relative;
            display: inline-block;
        }
        
        .tooltip .tooltip-text {
            visibility: hidden;
            background-color: rgba(0, 0, 0, 0.8);
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px 10px;
            position: absolute;
            z-index: 100;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s, transform 0.3s;
            font-size: 12px;
            white-space: nowrap;
            transform-origin: bottom center;
            transform: translateX(-50%) scale(0.9);
        }
        
        .tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
            transform: translateX(-50%) scale(1);
        }
        
        /* Animación de destello verde */
        @keyframes greenFlash {
            0% { background-color: rgba(34, 197, 94, 0); }
            30% { background-color: rgba(34, 197, 94, 0.3); }
            100% { background-color: rgba(34, 197, 94, 0); }
        }
        
        .green-flash {
            animation: greenFlash 1.5s ease;
        }
        
        /* Animación de celebración para productos completados */
        @keyframes celebrationFlash {
            0% { box-shadow: 0 0 0 rgba(16, 185, 129, 0); }
            25% { box-shadow: 0 0 20px rgba(16, 185, 129, 0.8); }
            50% { box-shadow: 0 0 10px rgba(16, 185, 129, 0.4); }
            75% { box-shadow: 0 0 20px rgba(16, 185, 129, 0.8); }
            100% { box-shadow: 0 0 0 rgba(16, 185, 129, 0); }
        }
        
        .celebration-animation {
            animation: celebrationFlash 2s infinite;
        }
        
        /* Animación para productos cercanos a la meta */
        @keyframes warningFlash {
            0% { box-shadow: 0 0 0 rgba(249, 115, 22, 0); }
            25% { box-shadow: 0 0 20px rgba(249, 115, 22, 0.8); }
            50% { box-shadow: 0 0 10px rgba(249, 115, 22, 0.4); }
            75% { box-shadow: 0 0 20px rgba(249, 115, 22, 0.8); }
            100% { box-shadow: 0 0 0 rgba(249, 115, 22, 0); }
        }
        
        .warning-animation {
            animation: warningFlash 2s infinite;
        }
        
        /* Toast de notificación */
        .toast-container {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9999;
            max-width: 380px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .toast {
            background-color: #ffffff;
            border-radius: 0.75rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1), 0 1px 3px rgba(0, 0, 0, 0.05);
            padding: 1rem;
            margin-bottom: 0.75rem;
            transition: transform 0.3s ease, opacity 0.3s ease;
            transform: translateX(100%);
            opacity: 0;
            display: flex;
            align-items: flex-start;
            overflow: hidden;
        }
        
        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }
        
        .toast.hide {
            transform: translateX(100%);
            opacity: 0;
        }
        
        .toast-icon {
            margin-right: 12px;
            font-size: 18px;
        }
        
        .toast-content {
            flex: 1;
        }
        
        .toast-success {
            border-left: 4px solid #10b981;
        }
        
        .toast-warning {
            border-left: 4px solid #f59e0b;
        }
        
        .toast-error {
            border-left: 4px solid #ef4444;
        }
        
        .toast-info {
            border-left: 4px solid #3b82f6;
        }
        
        /* Mejoras para móviles */
        @media (max-width: 640px) {
            .mobile-compact {
                padding: 0.5rem !important;
            }
            
            .mobile-compact .text-3xl {
                font-size: 1.5rem !important;
            }
            
            .mode-switch {
                display: flex;
                width: 100%;
                max-width: 300px;
                margin: 0 auto;
                border-radius: 9999px;
                overflow: hidden;
                background-color: #e5e7eb;
                border: 1px solid #d1d5db;
            }
            
            .mode-switch a {
                flex: 1;
                text-align: center;
                padding: 8px 0;
                font-weight: 500;
                transition: all 0.2s ease;
                font-size: 14px;
            }
            
            .mode-switch a.active {
                background-color: #4f46e5;
                color: white;
            }
            
            .bottom-padding {
                padding-bottom: 20px;
            }
            
            .mobile-action-buttons {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 5px;
            }
            
            .mobile-action-buttons button {
                padding: 8px !important;
                font-size: 14px !important;
            }
            
            .mobile-control-bar {
                flex-direction: column !important;
            }
            
            .mobile-control-bar form {
                width: 100%;
                margin-bottom: 10px;
            }
            
            .small-text-mobile {
                font-size: 0.75rem !important;
            }
            
            .shift-selector {
                font-size: 0.75rem;
                padding: 0.375rem 0.75rem !important;
            }
            
            .filter-dropdown {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }
        
        /* Estilos para el resumen */
        .summary-card {
            background: linear-gradient(135deg, #4f46e5, #818cf8);
            border-radius: 1rem;
            color: white;
            overflow: hidden;
            position: relative;
            box-shadow: 0 15px 30px -10px rgba(79, 70, 229, 0.3);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px -15px rgba(79, 70, 229, 0.4);
        }
        
        .summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="white" fill-opacity="0.05" d="M0,128L48,149.3C96,171,192,213,288,218.7C384,224,480,192,576,165.3C672,139,768,117,864,128C960,139,1056,181,1152,186.7C1248,192,1344,160,1392,144L1440,128L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') no-repeat bottom;
            background-size: cover;
            opacity: 0.2;
        }
        
        .summary-counter {
            font-size: 2rem;
            line-height: 1;
            font-weight: 600;
        }
        
        .summary-label {
            font-size: 0.875rem;
            opacity: 0.8;
        }
        
        .trend-up {
            color: #10b981;
            font-weight: bold;
        }
        
        .trend-down {
            color: #ef4444;
            font-weight: bold;
        }
        
        /* Estilos para filtros y badges */
        .filter-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .filter-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        /* Estilos para el favorito */
        .favorite-button {
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .favorite-button:hover {
            transform: scale(1.2);
        }
        
        .favorite-active {
            color: #eab308;
            animation: starPulse 1s 1;
        }
        
        @keyframes starPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.5); }
            100% { transform: scale(1); }
        }
        
        /* Nuevos estilos mejorados */
        .product-card {
            border-radius: 1rem;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .product-header {
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding-bottom: 0.75rem;
        }
        
        .gradient-button {
            background: linear-gradient(to right, #4f46e5, #818cf8);
            color: white;
            transition: all 0.3s ease;
        }
        
        .gradient-button:hover {
            background: linear-gradient(to right, #4338ca, #6366f1);
            box-shadow: 0 4px 6px rgba(79, 70, 229, 0.3);
        }
        
        .progress-bar-bg {
            background: linear-gradient(to right, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
            overflow: hidden;
            border-radius: 9999px;
        }
        
        .progress-value {
            padding: 0 0.5rem;
            font-weight: bold;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 9999px;
        }
        
        .search-input {
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.3);
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        /* Animaciones adicionales */
        @keyframes slideUp {
            from { transform: translateY(10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .animate-slide-up {
            animation: slideUp 0.3s ease forwards;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease forwards;
        }
        
        /* Estilo para botones de acción */
        .action-button {
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        
        .action-button::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: translate(-50%, -50%) scale(1);
            transition: all 0.5s ease;
        }
        
        .action-button:active::after {
            width: 200px;
            height: 200px;
            opacity: 0;
            transform: translate(-50%, -50%) scale(4);
        }
        
        /* Mejora en los iconos */
        .icon-container {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .icon-container:hover {
            background: rgba(0, 0, 0, 0.1);
            transform: rotate(15deg);
        }
        
        /* Mejoras en las tarjetas de resumen */
        .info-card {
            border-radius: 1rem;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.03);
        }
        
        .info-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
        }
        
        /* Estilos para el indicador de actualización automática */
        .auto-update-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
            transition: background-color 0.3s ease;
        }
        
        .auto-update-active {
            background-color: #10b981;
            box-shadow: 0 0 5px #10b981;
            animation: pulse 1.5s infinite;
        }
        
        .auto-update-inactive {
            background-color: #9ca3af;
        }
        
        /* Nueva sección de Todos los Turnos mejorada */
        .all-shifts-card {
            background: linear-gradient(135deg, #3730a3, #6366f1);
            border-radius: 1rem;
            color: white;
            overflow: hidden;
            position: relative;
            box-shadow: 0 15px 30px -10px rgba(79, 70, 229, 0.3);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .all-shifts-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px -15px rgba(79, 70, 229, 0.4);
        }
        
        .shift-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            background-color: rgba(255, 255, 255, 0.2);
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-left: auto;
            margin-right: auto;
        }
        
        .shift-data-block {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 0.75rem;
            padding: 1rem;
            transition: all 0.3s ease;
        }
        
        .shift-data-block:hover {
            background-color: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }
        
        .shift-title {
            font-weight: 600;
            font-size: 1.125rem;
            margin-bottom: 0.5rem;
        }
        
        .shift-progress-bar {
            height: 0.5rem;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }
        
        .shift-progress-value {
            height: 100%;
            border-radius: 1rem;
        }
        
        .morning-progress {
            background: linear-gradient(to right, #fbbf24, #f59e0b);
        }
        
        .afternoon-progress {
            background: linear-gradient(to right, #f97316, #ea580c);
        }
        
        .night-progress {
            background: linear-gradient(to right, #8b5cf6, #7c3aed);
        }
        
        .shift-stat {
            display: flex;
            justify-content: space-between;
            margin-top: 0.5rem;
            font-size: 0.875rem;
        }
        
        .shift-stat-label {
            color: rgba(255, 255, 255, 0.7);
        }
        
        .shift-stat-value {
            font-weight: 600;
        }
        
        /* Mejoras en las tarjetas de resumen responsivas */
        @media (max-width: 768px) {
            .shift-data-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }
        
        @media (min-width: 769px) {
            .shift-data-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 1rem;
            }
        }
    </style>
</head>
<body class="min-h-screen" id="mainBody">
    <!-- Contenedor para notificaciones toast -->
    <div id="toastContainer" class="toast-container"></div>
    
    <div class="min-h-screen p-4 mobile-compact bottom-padding">
        <div class="max-w-7xl mx-auto">
            <div class="flex justify-between items-center mb-6 top-bar">
                <h1 class="text-2xl md:text-3xl font-bold text-indigo-900 flex items-center">
                    <i class="fas fa-chart-line mr-2 text-indigo-600"></i>
                    Monitoreo de Producción
                </h1>
                
                <!-- Solo mostramos el selector de modo en versión móvil si estamos en modo edición -->
                <?php if ($mode === EDIT_MODE): ?>
                <div class="mode-switch md:hidden mb-4">
                    <a href="<?php echo $readModeUrl; ?>" class="<?php echo $mode === READ_MODE ? 'active' : ''; ?>">
                        <i class="fas fa-eye mr-1"></i> Lectura
                    </a>
                    <a href="<?php echo $editModeUrl; ?>" class="<?php echo $mode === EDIT_MODE ? 'active' : ''; ?>">
                        <i class="fas fa-edit mr-1"></i> Edición
                    </a>
                </div>
                <?php endif; ?>
                
                <div class="flex items-center gap-3">
                    <!-- Indicador de actualización automática -->
                    <div class="flex items-center mr-2">
                        <span id="autoUpdateIndicator" class="auto-update-indicator auto-update-active"></span>
                        <span class="text-xs text-gray-500 hidden md:inline">Auto</span>
                    </div>
                    
                    <!-- Botón para controlar actualizaciones automáticas -->
                    <div class="relative">
                        <button id="togglePollingButton" class="p-2 bg-white hover:bg-gray-100 text-gray-700 rounded-full relative tooltip shadow-sm">
                            <i class="fas fa-sync-alt"></i>
                            <span class="tooltip-text">Actualizaciones en tiempo real</span>
                            <span id="pollingIndicator" class="w-3 h-3 bg-green-500 rounded-full absolute bottom-0 right-0"></span>
                        </button>
                    </div>
                    
                    <!-- Botón de notificaciones -->
                    <div class="relative">
                        <button id="notificationButton" class="p-2 bg-white hover:bg-gray-100 text-gray-700 rounded-full relative tooltip shadow-sm">
                            <i class="fas fa-bell"></i>
                            <span class="tooltip-text">Notificaciones</span>
                            <?php if ($unreadCount > 0): ?>
                                <span class="notification-badge pulse-animation"><?php echo $unreadCount; ?></span>
                            <?php endif; ?>
                        </button>
                        
                        <!-- Panel de notificaciones (oculto por defecto) -->
                        <div id="notificationPanel" class="hidden absolute right-0 mt-2 w-96 bg-white shadow-lg rounded-lg z-50 notification-panel">
                            <div class="p-3 border-b flex justify-between items-center sticky top-0 bg-white z-10">
                                <h3 class="font-bold text-gray-800">Notificaciones</h3>
                                <div class="flex gap-2">
                                    <button id="markAllReadBtn" class="text-sm px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 tooltip">
                                        <i class="fas fa-check-double"></i>
                                        <span class="tooltip-text">Marcar todas como leídas</span>
                                    </button>
                                    <button id="clearAllNotificationsBtn" class="text-sm px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 text-red-500 tooltip">
                                        <i class="fas fa-trash"></i>
                                        <span class="tooltip-text">Borrar todas</span>
                                    </button>
                                </div>
                            </div>
                            <div id="notificationContainer">
                                <?php if (count($notifications) > 0): ?>
                                    <div class="p-3">
                                        <?php foreach ($notifications as $notification): ?>
                                            <div class="mb-3 p-3 border-l-4 rounded-r-lg relative notification-item <?php echo !$notification['read'] ? 'bg-blue-50' : 'bg-white'; ?> 
                                                <?php echo $notification['type'] === 'error' ? 'border-red-500' : 
                                                    ($notification['type'] === 'warning' ? 'border-yellow-500' : 
                                                    ($notification['type'] === 'success' ? 'border-green-500' : 'border-blue-500')); ?>"
                                                data-id="<?php echo $notification['id']; ?>">
                                                <div class="flex justify-between">
                                                    <p class="font-bold text-sm text-gray-800"><?php echo htmlspecialchars($notification['productName']); ?></p>
                                                    <span class="text-xs text-gray-500"><?php echo date('d/m H:i', $notification['timestamp']); ?></span>
                                                </div>
                                                <p class="text-sm">
                                                    <span class="
                                                        <?php echo $notification['type'] === 'error' ? 'text-red-600' : 
                                                            ($notification['type'] === 'warning' ? 'text-yellow-600' : 
                                                            ($notification['type'] === 'success' ? 'text-green-600' : 'text-blue-600')); ?>">
                                                        <?php echo htmlspecialchars($notification['message']); ?>
                                                    </span>
                                                    <?php if ($notification['shift'] !== 'all'): ?>
                                                    <span class="text-gray-500">
                                                        (<?php echo $notification['shift'] === 'morning' ? 'Mañana' : ($notification['shift'] === 'afternoon' ? 'Tarde' : 'Noche'); ?>)
                                                    </span>
                                                    <?php endif; ?>
                                                </p>
                                                <?php if (!$notification['read']): ?>
                                                    <button class="absolute top-2 right-2 text-gray-400 hover:text-gray-600 mark-read-btn" data-id="<?php echo $notification['id']; ?>">
                                                        <i class="fas fa-check text-xs"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="p-8 text-center text-gray-500">
                                        <i class="fas fa-bell-slash text-4xl mb-3 opacity-30"></i>
                                        <p>No hay notificaciones</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Enlaces de modo -->
                    <div class="hidden md:flex">
                        <?php if ($mode === EDIT_MODE): ?>
                            <a href="<?php echo $readModeUrl; ?>" class="px-3 py-1 bg-indigo-600 text-white rounded-lg flex items-center gap-1 tooltip transition-colors hover:bg-indigo-700">
                                <i class="fas fa-eye"></i>
                                <span>Lectura</span>
                            </a>
                        <?php else: ?>
                            <a href="<?php echo $editModeUrl; ?>" class="px-3 py-1 bg-indigo-600 text-white rounded-lg flex items-center gap-1 tooltip transition-colors hover:bg-indigo-700">
                                <i class="fas fa-edit"></i>
                                <span>Edición</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- SECCIÓN DE TODOS LOS TURNOS MEJORADA -->
            <?php if ($shiftFilter === 'all'): ?>
            <div class="mb-8">
                <div class="all-shifts-card p-6">
                    <div class="relative z-10">
                        <div class="text-center mb-6">
                            <h2 class="text-xl font-bold">Progreso Global de Producción</h2>
                            <p class="text-sm text-white text-opacity-80 mt-1">Vista integrada de todos los turnos</p>
                        </div>
                        
                        <div class="shift-data-grid">
                            <!-- Turno Mañana -->
                            <div class="shift-data-block" id="morning-shift-block">
                                <div class="text-center mb-3">
                                    <div class="shift-icon">
                                        <i class="fas fa-sun"></i>
                                    </div>
                                    <h3 class="shift-title">Turno Mañana</h3>
                                </div>
                                
                                <div class="shift-progress-bar">
                                    <div class="shift-progress-value morning-progress" id="morning-progress-value" style="width: <?php echo $productionSummary['morning_progress']; ?>%"></div>
                                </div>
                                
                                <div class="text-center mb-2">
                                    <span class="text-sm bg-white bg-opacity-20 px-2 py-0.5 rounded-full" id="morning-progress-percent">
                                        <?php echo $productionSummary['morning_progress']; ?>% Completado
                                    </span>
                                </div>
                                
                                <div class="shift-stat">
                                    <span class="shift-stat-label">Productos activos:</span>
                                    <span class="shift-stat-value"><?php echo $productionSummary['morning_products']; ?></span>
                                </div>
                                
                                <div class="shift-stat">
                                    <span class="shift-stat-label">Tarimas producidas:</span>
                                    <span class="shift-stat-value" id="morning-pallets-produced"><?php echo $productionSummary['morning_pallets_produced']; ?></span>
                                </div>
                                
                                <div class="shift-stat">
                                    <span class="shift-stat-label">Tarimas restantes:</span>
                                    <span class="shift-stat-value" id="morning-pallets-remaining"><?php echo $productionSummary['morning_pallets_remaining']; ?></span>
                                </div>
                                
                                <div class="text-center mt-4">
                                    <a href="<?php echo $morningShiftUrl; ?>" class="inline-block px-4 py-2 bg-white bg-opacity-10 hover:bg-opacity-20 rounded-lg text-sm font-medium transition-all">
                                        Ver detalles <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                            </div>
                            
                            <!-- Turno Tarde -->
                            <div class="shift-data-block" id="afternoon-shift-block">
                                <div class="text-center mb-3">
                                    <div class="shift-icon">
                                        <i class="fas fa-cloud-sun"></i>
                                    </div>
                                    <h3 class="shift-title">Turno Tarde</h3>
                                </div>
                                
                                <div class="shift-progress-bar">
                                    <div class="shift-progress-value afternoon-progress" id="afternoon-progress-value" style="width: <?php echo $productionSummary['afternoon_progress']; ?>%"></div>
                                </div>
                                
                                <div class="text-center mb-2">
                                    <span class="text-sm bg-white bg-opacity-20 px-2 py-0.5 rounded-full" id="afternoon-progress-percent">
                                        <?php echo $productionSummary['afternoon_progress']; ?>% Completado
                                    </span>
                                </div>
                                
                                <div class="shift-stat">
                                    <span class="shift-stat-label">Productos activos:</span>
                                    <span class="shift-stat-value"><?php echo $productionSummary['afternoon_products']; ?></span>
                                </div>
                                
                                <div class="shift-stat">
                                    <span class="shift-stat-label">Tarimas producidas:</span>
                                    <span class="shift-stat-value" id="afternoon-pallets-produced"><?php echo $productionSummary['afternoon_pallets_produced']; ?></span>
                                </div>
                                
                                <div class="shift-stat">
                                    <span class="shift-stat-label">Tarimas restantes:</span>
                                    <span class="shift-stat-value" id="afternoon-pallets-remaining"><?php echo $productionSummary['afternoon_pallets_remaining']; ?></span>
                                </div>
                                
                                <div class="text-center mt-4">
                                    <a href="<?php echo $afternoonShiftUrl; ?>" class="inline-block px-4 py-2 bg-white bg-opacity-10 hover:bg-opacity-20 rounded-lg text-sm font-medium transition-all">
                                        Ver detalles <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                            </div>
                            
                            <!-- Turno Noche -->
                            <div class="shift-data-block" id="night-shift-block">
                                <div class="text-center mb-3">
                                    <div class="shift-icon">
                                        <i class="fas fa-moon"></i>
                                    </div>
                                    <h3 class="shift-title">Turno Noche</h3>
                                </div>
                                
                                <div class="shift-progress-bar">
                                    <div class="shift-progress-value night-progress" id="night-progress-value" style="width: <?php echo $productionSummary['night_progress']; ?>%"></div>
                                </div>
                                
                                <div class="text-center mb-2">
                                    <span class="text-sm bg-white bg-opacity-20 px-2 py-0.5 rounded-full" id="night-progress-percent">
                                        <?php echo $productionSummary['night_progress']; ?>% Completado
                                    </span>
                                </div>
                                
                                <div class="shift-stat">
                                    <span class="shift-stat-label">Productos activos:</span>
                                    <span class="shift-stat-value"><?php echo $productionSummary['night_products']; ?></span>
                                </div>
                                
                                <div class="shift-stat">
                                    <span class="shift-stat-label">Tarimas producidas:</span>
                                    <span class="shift-stat-value" id="night-pallets-produced"><?php echo $productionSummary['night_pallets_produced']; ?></span>
                                </div>
                                
                                <div class="shift-stat">
                                    <span class="shift-stat-label">Tarimas restantes:</span>
                                    <span class="shift-stat-value" id="night-pallets-remaining"><?php echo $productionSummary['night_pallets_remaining']; ?></span>
                                </div>
                                
                                <div class="text-center mt-4">
                                    <a href="<?php echo $nightShiftUrl; ?>" class="inline-block px-4 py-2 bg-white bg-opacity-10 hover:bg-opacity-20 rounded-lg text-sm font-medium transition-all">
                                        Ver detalles <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-6 pt-4 border-t border-white border-opacity-20">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div class="bg-white bg-opacity-10 p-3 rounded-lg text-center">
                                    <div class="text-sm text-white text-opacity-70">Productos Totales</div>
                                    <div class="font-bold text-xl"><?php echo $productionSummary['total_products']; ?></div>
                                </div>
                                <div class="bg-white bg-opacity-10 p-3 rounded-lg text-center">
                                    <div class="text-sm text-white text-opacity-70">Completados</div>
                                    <div class="font-bold text-xl"><?php echo $productionSummary['total_completed']; ?></div>
                                </div>
                                <div class="bg-white bg-opacity-10 p-3 rounded-lg text-center">
                                    <div class="text-sm text-white text-opacity-70">Tarimas Producidas</div>
                                    <div class="font-bold text-xl"><?php echo $productionSummary['total_pallets_produced']; ?></div>
                                </div>
                                <div class="bg-white bg-opacity-10 p-3 rounded-lg text-center">
                                    <div class="text-sm text-white text-opacity-70">Progreso General</div>
                                    <div class="font-bold text-xl"><?php echo $productionSummary['overall_progress']; ?>%</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Resumen de producción para turno específico -->
            <div class="mb-8">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Tarjeta de progreso por turno rediseñada -->
                    <div class="summary-card p-5">
                        <div class="relative z-10">
                            <div class="text-center mb-4">
                                <span class="text-sm font-bold px-3 py-1.5 bg-white bg-opacity-30 rounded-full inline-block shadow-sm">
                                    <i class="fas fa-chart-pie mr-1"></i> Progreso por Turno
                                </span>
                            </div>
<!-- Progreso Mañana - Rediseñado -->
<div id="morning-progress-section" class="mb-5 bg-white bg-opacity-10 p-3 rounded-lg cursor-pointer hover:bg-white hover:bg-opacity-20 transition-all">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-sm font-bold flex items-center">
                                        <i class="fas fa-sun text-yellow-300 mr-2 text-lg"></i> Mañana
                                    </span>
                                    <span class="text-sm font-semibold bg-white bg-opacity-20 px-2 py-1 rounded-full" id="morning-pallets-summary">
                                        <?php echo $productionSummary['morning_pallets_produced']; ?> de 
                                        <?php echo $productionSummary['morning_pallets_produced'] + $productionSummary['morning_pallets_remaining']; ?> tarimas
                                    </span>
                                </div>
                                <div class="flex items-center">
                                    <div class="h-4 bg-white bg-opacity-20 rounded-full w-full overflow-hidden border border-white border-opacity-30">
                                        <div class="h-full bg-yellow-400 shadow-sm" id="morning-progress-bar" style="width: <?php echo $productionSummary['morning_progress']; ?>%"></div>
                                    </div>
                                </div>
                                <div class="text-right text-sm mt-1 font-bold flex justify-end items-center">
                                    <div class="bg-black bg-opacity-30 px-2 py-0.5 rounded-full" id="morning-progress-text">
                                        <?php echo $productionSummary['morning_progress']; ?>%
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Progreso Tarde - Rediseñado -->
                            <div id="afternoon-progress-section" class="mb-5 bg-white bg-opacity-10 p-3 rounded-lg cursor-pointer hover:bg-white hover:bg-opacity-20 transition-all">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-sm font-bold flex items-center">
                                        <i class="fas fa-cloud-sun text-orange-300 mr-2 text-lg"></i> Tarde
                                    </span>
                                    <span class="text-sm font-semibold bg-white bg-opacity-20 px-2 py-1 rounded-full" id="afternoon-pallets-summary">
                                        <?php echo $productionSummary['afternoon_pallets_produced']; ?> de 
                                        <?php echo $productionSummary['afternoon_pallets_produced'] + $productionSummary['afternoon_pallets_remaining']; ?> tarimas
                                    </span>
                                </div>
                                <div class="flex items-center">
                                    <div class="h-4 bg-white bg-opacity-20 rounded-full w-full overflow-hidden border border-white border-opacity-30">
                                        <div class="h-full bg-red-500 shadow-sm" id="afternoon-progress-bar" style="width: <?php echo $productionSummary['afternoon_progress']; ?>%"></div>
                                    </div>
                                </div>
                                <div class="text-right text-sm mt-1 font-bold flex justify-end items-center">
                                    <div class="bg-black bg-opacity-30 px-2 py-0.5 rounded-full" id="afternoon-progress-text">
                                        <?php echo $productionSummary['afternoon_progress']; ?>%
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Progreso Noche - Rediseñado -->
                            <div id="night-progress-section" class="bg-white bg-opacity-10 p-3 rounded-lg cursor-pointer hover:bg-white hover:bg-opacity-20 transition-all">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-sm font-bold flex items-center">
                                        <i class="fas fa-moon text-indigo-300 mr-2 text-lg"></i> Noche
                                    </span>
                                    <span class="text-sm font-semibold bg-white bg-opacity-20 px-2 py-1 rounded-full" id="night-pallets-summary">
                                        <?php echo $productionSummary['night_pallets_produced']; ?> de 
                                        <?php echo $productionSummary['night_pallets_produced'] + $productionSummary['night_pallets_remaining']; ?> tarimas
                                    </span>
                                </div>
                                <div class="flex items-center">
                                    <div class="h-4 bg-white bg-opacity-20 rounded-full w-full overflow-hidden border border-white border-opacity-30">
                                        <div class="h-full bg-purple-500 shadow-sm" id="night-progress-bar" style="width: <?php echo $productionSummary['night_progress']; ?>%"></div>
                                    </div>
                                </div>
                                <div class="text-right text-sm mt-1 font-bold flex justify-end items-center">
                                    <div class="bg-black bg-opacity-30 px-2 py-0.5 rounded-full" id="night-progress-text">
                                        <?php echo $productionSummary['night_progress']; ?>%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl p-5 shadow-sm info-card">
                        <h3 class="text-gray-500 text-sm font-semibold mb-3">Estado de Producción</h3>
                        <div class="flex flex-col gap-2">
                            <div class="flex justify-between items-center bg-green-50 p-2 rounded-lg">
                                <span class="text-sm flex items-center"><i class="fas fa-check-circle text-green-500 mr-1"></i> Completados</span>
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">
                                    <?php echo $productionSummary['total_completed']; ?> productos
                                </span>
                            </div>
                            <div class="flex justify-between items-center bg-yellow-50 p-2 rounded-lg">
                                <span class="text-sm flex items-center"><i class="fas fa-exclamation-circle text-yellow-500 mr-1"></i> Cercanos a meta</span>
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-semibold">
                                    <?php echo $productionSummary['total_close_to_goal']; ?> productos
                                </span>
                            </div>
                            <div class="flex justify-between items-center bg-blue-50 p-2 rounded-lg">
                                <span class="text-sm flex items-center"><i class="fas fa-hourglass-half text-blue-500 mr-1"></i> Pendientes</span>
                                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-semibold">
                                    <?php echo $productionSummary['total_active'] - $productionSummary['total_completed'] - $productionSummary['total_close_to_goal']; ?> productos
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl p-5 shadow-sm info-card">
                        <h3 class="text-gray-500 text-sm font-semibold mb-3">Distribución por Turnos</h3>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="text-center p-2 bg-blue-50 rounded-lg">
                                <div class="text-blue-600 font-bold text-xl"><?php echo $productionSummary['morning_products']; ?></div>
                                <div class="text-xs text-blue-800">Mañana</div>
                            </div>
                            <div class="text-center p-2 bg-orange-50 rounded-lg">
                                <div class="text-orange-600 font-bold text-xl"><?php echo $productionSummary['afternoon_products']; ?></div>
                                <div class="text-xs text-orange-800">Tarde</div>
                            </div>
                            <div class="text-center p-2 bg-indigo-50 rounded-lg">
                                <div class="text-indigo-600 font-bold text-xl"><?php echo $productionSummary['night_products']; ?></div>
                                <div class="text-xs text-indigo-800">Noche</div>
                            </div>
                        </div>
                        <div class="mt-3 text-center px-3 py-2 bg-gray-100 rounded-lg text-sm text-gray-700">
                            <span class="font-bold text-indigo-700"><?php echo $productionSummary['total_products']; ?></span> productos en total
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl p-5 shadow-sm info-card">
                        <h3 class="text-gray-500 text-sm font-semibold mb-3">Acciones Rápidas</h3>
                        <div class="space-y-2">
                            <?php if ($mode === EDIT_MODE): ?>
                                <button id="quickAddProductBtn" class="w-full py-2 px-3 bg-indigo-100 hover:bg-indigo-200 text-indigo-700 rounded-lg flex items-center gap-2 transition-colors">
                                    <i class="fas fa-plus-circle"></i>
                                    <span>Nuevo Producto</span>
                                </button>
                            <?php endif; ?>
                            <a href="<?php echo $completedStatusUrl; ?>" class="w-full py-2 px-3 bg-green-100 hover:bg-green-200 text-green-700 rounded-lg flex items-center gap-2 transition-colors">
                                <i class="fas fa-check-circle"></i>
                                <span>Ver Completados</span>
                            </a>
                            <a href="<?php echo $closeStatusUrl; ?>" class="w-full py-2 px-3 bg-yellow-100 hover:bg-yellow-200 text-yellow-700 rounded-lg flex items-center gap-2 transition-colors">
                                <i class="fas fa-exclamation-circle"></i>
                                <span>Ver Cercanos a Meta</span>
                            </a>
                            <a href="<?php echo $favoritesStatusUrl; ?>" class="w-full py-2 px-3 bg-amber-100 hover:bg-amber-200 text-amber-700 rounded-lg flex items-center gap-2 transition-colors">
                                <i class="fas fa-star"></i>
                                <span>Ver Favoritos</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 status-bar rounded-lg animate-slide-up" role="alert">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2 text-red-500"></i>
                        <p><?php echo $_SESSION['error']; ?></p>
                    </div>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 status-bar rounded-lg animate-slide-up" role="alert">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2 text-green-500"></i>
                        <p><?php echo $_SESSION['success']; ?></p>
                    </div>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <!-- Selector de turnos rápido -->
            <div class="mb-6">
                <div class="flex flex-wrap gap-2 justify-center">
                    <a href="<?php echo $allShiftUrl; ?>" class="shift-selector px-4 py-2 rounded-lg text-center flex-grow sm:flex-grow-0 <?php echo $shiftFilter === 'all' ? 'bg-gray-800 text-white active' : 'bg-white text-gray-800 hover:bg-gray-100 shadow-sm'; ?>">
                        <i class="fas fa-layer-group mr-1"></i> Todos los Turnos
                    </a>
                    <a href="<?php echo $morningShiftUrl; ?>" class="shift-selector px-4 py-2 rounded-lg text-center flex-grow sm:flex-grow-0 <?php echo $shiftFilter === 'morning' ? 'bg-blue-600 text-white active' : 'bg-white text-blue-600 hover:bg-blue-50 shadow-sm'; ?>">
                        <i class="fas fa-sun mr-1"></i> Mañana
                    </a>
                    <a href="<?php echo $afternoonShiftUrl; ?>" class="shift-selector px-4 py-2 rounded-lg text-center flex-grow sm:flex-grow-0 <?php echo $shiftFilter === 'afternoon' ? 'bg-orange-600 text-white active' : 'bg-white text-orange-600 hover:bg-orange-50 shadow-sm'; ?>">
                        <i class="fas fa-cloud-sun mr-1"></i> Tarde
                    </a>
                    <a href="<?php echo $nightShiftUrl; ?>" class="shift-selector px-4 py-2 rounded-lg text-center flex-grow sm:flex-grow-0 <?php echo $shiftFilter === 'night' ? 'bg-indigo-600 text-white active' : 'bg-white text-indigo-600 hover:bg-indigo-50 shadow-sm'; ?>">
                        <i class="fas fa-moon mr-1"></i> Noche
                    </a>
                </div>
            </div>

            <!-- Filtros por estado -->
            <div class="mb-6 flex flex-wrap gap-2 justify-center">
                <a href="<?php echo $allStatusUrl; ?>" class="filter-badge <?php echo $statusFilter === 'all' ? 'bg-gray-800 text-white' : 'bg-white text-gray-800 border border-gray-200 hover:bg-gray-100'; ?> shadow-sm">
                    <i class="fas fa-filter"></i>
                    <span>Todos</span>
                </a>
                <a href="<?php echo $pendingStatusUrl; ?>" class="filter-badge <?php echo $statusFilter === 'pending' ? 'bg-blue-600 text-white' : 'bg-white text-blue-600 border border-blue-200 hover:bg-blue-50'; ?> shadow-sm">
                    <i class="fas fa-hourglass-half"></i>
                    <span>Pendientes</span>
                </a>
                <a href="<?php echo $closeStatusUrl; ?>" class="filter-badge <?php echo $statusFilter === 'close' ? 'bg-yellow-500 text-white' : 'bg-white text-yellow-600 border border-yellow-200 hover:bg-yellow-50'; ?> shadow-sm">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>Cercanos a Meta</span>
                </a>
                <a href="<?php echo $completedStatusUrl; ?>" class="filter-badge <?php echo $statusFilter === 'completed' ? 'bg-green-600 text-white' : 'bg-white text-green-600 border border-green-200 hover:bg-green-50'; ?> shadow-sm">
                    <i class="fas fa-check-circle"></i>
                    <span>Completados</span>
                </a>
                <a href="<?php echo $favoritesStatusUrl; ?>" class="filter-badge <?php echo $statusFilter === 'favorites' ? 'bg-amber-500 text-white' : 'bg-white text-amber-600 border border-amber-200 hover:bg-amber-50'; ?> shadow-sm">
                    <i class="fas fa-star"></i>
                    <span>Favoritos</span>
                </a>
            </div>

            <!-- Barra de control -->
            <div class="bg-white p-4 rounded-lg shadow-sm mb-6 flex flex-wrap items-center justify-between gap-3 search-bar mobile-control-bar">
                <form action="" method="GET" class="flex items-center gap-2 flex-grow">
                    <input type="hidden" name="mode" value="<?php echo $mode; ?>">
                    <input type="hidden" name="shift" value="<?php echo $shiftFilter; ?>">
                    <input type="hidden" name="filter" value="<?php echo $statusFilter; ?>">
                    <div class="relative flex-grow">
                        <input
                            type="text"
                            name="search"
                            placeholder="Buscar producto..."
                            class="pl-10 pr-4 py-2 border border-gray-200 rounded-lg w-full focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent search-input"
                            value="<?php echo htmlspecialchars($searchTerm); ?>"
                        >
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                    </div>
                    
                    <button type="submit" class="bg-indigo-100 text-indigo-700 hover:bg-indigo-200 px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
                
                <?php if ($mode === EDIT_MODE): ?>
                    <button 
                        id="addProductButton"
                        class="gradient-button px-4 py-2 rounded-lg flex items-center gap-2 text-sm md:text-base shadow-sm transition-colors action-button"
                    >
                        <i class="fas fa-plus-circle"></i>
                        <span>Agregar Producto</span>
                    </button>
                <?php endif; ?>
            </div>

            <!-- Lista de Productos -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="productList">
                <?php if (count($filteredProducts) > 0): ?>
                    <?php foreach ($filteredProducts as $product): ?>
                        <?php 
                            // Obtener datos del turno seleccionado o del primer turno activo
                            $getActiveShift = function() use ($product, $shiftFilter) {
                                if ($shiftFilter !== 'all') return $shiftFilter;
                                if ($product['shifts']['morning']['active']) return 'morning';
                                if ($product['shifts']['afternoon']['active']) return 'afternoon';
                                if ($product['shifts']['night']['active']) return 'night';
                                return 'morning'; // Default
                            };
                            
                            $currentShift = $getActiveShift();
                            $shiftData = $product['shifts'][$currentShift];
                            $totalBoxes = ($shiftData['pallets'] * $config['boxes_per_pallet']) + $shiftData['boxes'];
                            $progress = calculateProgress($totalBoxes, $shiftData['productionGoal']);
                            $progressColor = getProgressColor($progress, $config);
                            $continues = continuesNextShift($product, $currentShift);
                            $closeToGoal = isCloseToGoal($product, $currentShift, $config['boxes_per_pallet']);
                            $isComplete = $totalBoxes >= $shiftData['productionGoal'];
                            $isFavorite = in_array($product['id'], $config['favorites']);
                            
                            // Calcular tarimas y cajas faltantes
                            $remainingBoxes = max(0, $shiftData['productionGoal'] - $totalBoxes);
                            $remainingData = calculatePallets($remainingBoxes, $config['boxes_per_pallet']);
                            
                            // Verificar notificaciones específicas para este producto
                            $hasWarning = !$continues;
                            $hasInfo = $closeToGoal;
                            
                            // Calcular si los turnos están completados
                            $morningCompleted = false;
                            if ($product['shifts']['morning']['active']) {
                                $morningBoxes = ($product['shifts']['morning']['pallets'] * $config['boxes_per_pallet']) + $product['shifts']['morning']['boxes'];
                                $morningCompleted = $morningBoxes >= $product['shifts']['morning']['productionGoal']; 
                            }
                            
                            $afternoonCompleted = false;
                            if ($product['shifts']['afternoon']['active']) {
                                $afternoonBoxes = ($product['shifts']['afternoon']['pallets'] * $config['boxes_per_pallet']) + $product['shifts']['afternoon']['boxes'];
                                $afternoonCompleted = $afternoonBoxes >= $product['shifts']['afternoon']['productionGoal']; 
                            }
                            
                            $nightCompleted = false;
                            if ($product['shifts']['night']['active']) {
                                $nightBoxes = ($product['shifts']['night']['pallets'] * $config['boxes_per_pallet']) + $product['shifts']['night']['boxes'];
                                $nightCompleted = $nightBoxes >= $product['shifts']['night']['productionGoal']; 
                            }
                        ?>
                        <div id="product-<?php echo $product['id']; ?>" class="product-card bg-white rounded-xl shadow-sm overflow-hidden card-hover transition-all <?php echo $hasWarning ? 'border-l-4 border-red-500' : ($hasInfo ? 'border-l-4 border-yellow-500' : ($isComplete ? 'border-l-4 border-green-500' : '')); ?> relative <?php echo $isComplete ? 'celebration-animation' : ($closeToGoal && !$isComplete ? 'warning-animation' : ''); ?> animate-fade-in">
                            <div class="p-5">
                                <div class="flex justify-between items-start mb-4 product-header">
                                    <div class="flex-1">
                                        <h3 class="font-bold text-lg text-gray-800"><?php echo htmlspecialchars($product['name']); ?></h3>
                                        <?php if (isset($product['last_update'])): ?>
                                            <div class="text-xs text-gray-500 flex items-center">
                                                <i class="fas fa-history mr-1"></i>
                                                <span id="last-update-<?php echo $product['id']; ?>"><?php echo date('d/m/Y H:i', $product['last_update']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <div 
                                            class="favorite-button tooltip <?php echo $isFavorite ? 'favorite-active' : 'text-gray-300 hover:text-gray-500'; ?>"
                                            data-id="<?php echo $product['id']; ?>"
                                        >
                                            <i class="fas fa-star text-lg"></i>
                                            <span class="tooltip-text"><?php echo $isFavorite ? 'Quitar de favoritos' : 'Añadir a favoritos'; ?></span>
                                        </div>
                                        
                                        <?php if ($mode === EDIT_MODE): ?>
                                            <div class="flex space-x-1">
                                                <button 
                                                    onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)"
                                                    class="text-indigo-600 hover:text-indigo-800 tooltip p-1 icon-container"
                                                >
                                                    <i class="fas fa-edit"></i>
                                                    <span class="tooltip-text">Editar</span>
                                                </button>
                                                <button 
                                                    onclick="confirmDeleteProduct(<?php echo $product['id']; ?>, '<?php echo addslashes(htmlspecialchars($product['name'])); ?>')"
                                                    class="text-red-500 hover:text-red-700 tooltip p-1 icon-container"
                                                >
                                                    <i class="fas fa-trash"></i>
                                                    <span class="tooltip-text">Eliminar</span>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="space-y-5">
                                    <!-- NUEVA SECCIÓN: Turnos Programados con turno actual en azul y completados en rojo -->
                                    <div class="bg-gray-50 p-4 rounded-xl">
                                        <h4 class="font-bold text-sm text-gray-700 mb-3 text-center">Turnos Programados</h4>
                                        <div class="flex flex-wrap justify-center gap-3">
                                            <?php if ($product['shifts']['morning']['active']): ?>
                                                <div class="text-center p-2 rounded-lg shadow-sm <?php echo $currentShift === 'morning' ? 'bg-blue-500 text-white' : ($morningCompleted ? 'bg-red-500 text-white' : 'bg-blue-100 text-blue-800'); ?>">
                                                    <i class="fas fa-sun text-lg"></i>
                                                    <div class="text-xs mt-1 font-medium">Mañana</div>
                                                    <div class="text-xs mt-1 font-bold">
                                                        Meta: <?php echo $product['shifts']['morning']['productionGoal']; ?>
                                                    </div>
                                                    <?php if ($currentShift === 'morning'): ?>
                                                        <div class="mt-1 text-xs font-semibold bg-white bg-opacity-20 rounded-full p-1">
                                                            Actual
                                                        </div>
                                                    <?php elseif ($morningCompleted): ?>
                                                        <div class="mt-1 text-xs font-semibold bg-white bg-opacity-20 rounded-full p-1">
                                                            Completado
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($product['shifts']['afternoon']['active']): ?>
                                                <div class="text-center p-2 rounded-lg shadow-sm <?php echo $currentShift === 'afternoon' ? 'bg-blue-500 text-white' : ($afternoonCompleted ? 'bg-red-500 text-white' : 'bg-orange-100 text-orange-800'); ?>">
                                                    <i class="fas fa-cloud-sun text-lg"></i>
                                                    <div class="text-xs mt-1 font-medium">Tarde</div>
                                                    <div class="text-xs mt-1 font-bold">
                                                        Meta: <?php echo $product['shifts']['afternoon']['productionGoal']; ?>
                                                    </div>
                                                    <?php if ($currentShift === 'afternoon'): ?>
                                                        <div class="mt-1 text-xs font-semibold bg-white bg-opacity-20 rounded-full p-1">
                                                            Actual
                                                        </div>
                                                    <?php elseif ($afternoonCompleted): ?>
                                                        <div class="mt-1 text-xs font-semibold bg-white bg-opacity-20 rounded-full p-1">
                                                            Completado
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($product['shifts']['night']['active']): ?>
                                                <div class="text-center p-2 rounded-lg shadow-sm <?php echo $currentShift === 'night' ? 'bg-blue-500 text-white' : ($nightCompleted ? 'bg-red-500 text-white' : 'bg-indigo-100 text-indigo-800'); ?>">
                                                    <i class="fas fa-moon text-lg"></i>
                                                    <div class="text-xs mt-1 font-medium">Noche</div>
                                                    <div class="text-xs mt-1 font-bold">
                                                        Meta: <?php echo $product['shifts']['night']['productionGoal']; ?>
                                                    </div>
                                                    <?php if ($currentShift === 'night'): ?>
                                                        <div class="mt-1 text-xs font-semibold bg-white bg-opacity-20 rounded-full p-1">
                                                            Actual
                                                        </div>
                                                    <?php elseif ($nightCompleted): ?>
                                                        <div class="mt-1 text-xs font-semibold bg-white bg-opacity-20 rounded-full p-1">
                                                            Completado
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Información de Producción -->
                                    <div class="bg-gray-50 rounded-xl p-4">
                                        <div class="flex justify-between mb-3">
                                            <div>
                                                <div class="text-xs text-gray-500">Meta</div>
                                                <div class="font-bold text-lg"><?php echo $shiftData['productionGoal']; ?> cajas</div>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-xs text-gray-500">Producción Actual</div>
                                                <div id="total-boxes-<?php echo $product['id']; ?>-<?php echo $currentShift; ?>" class="font-bold text-lg"><?php echo $totalBoxes; ?> cajas</div>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-3 gap-3 mb-3">
                                            <div class="bg-white p-3 rounded-lg text-center shadow-sm">
                                                <div class="text-xs text-gray-500">Tarimas</div>
                                                <div id="pallets-<?php echo $product['id']; ?>-<?php echo $currentShift; ?>" class="font-bold text-blue-600"><?php echo $shiftData['pallets']; ?></div>
                                            </div>
                                            <div class="bg-white p-3 rounded-lg text-center shadow-sm">
                                                <div class="text-xs text-gray-500">Cajas</div>
                                                <div id="boxes-<?php echo $product['id']; ?>-<?php echo $currentShift; ?>" class="font-bold text-blue-600"><?php echo $shiftData['boxes']; ?></div>
                                            </div>
                                            <div class="bg-white p-3 rounded-lg text-center shadow-sm">
                                                <div class="text-xs text-gray-500">Meta Total</div>
                                                <div class="font-bold text-blue-600"><?php echo ceil($shiftData['productionGoal'] / $config['boxes_per_pallet']); ?> tarimas</div>
                                            </div>
                                        </div>

                                        <!-- Información de lo que falta -->
                                        <?php if (!$isComplete): ?>
                                            <div class="bg-white p-4 rounded-lg shadow-sm">
                                                <div class="flex items-center mb-2">
                                                    <div class="h-2 flex-grow bg-gray-200 rounded-full">
                                                        <div class="h-2 <?php echo $progressColor; ?> rounded-full" style="width: <?php echo $progress; ?>%"></div>
                                                    </div>
                                                    <div class="ml-3 text-sm font-bold bg-gray-100 rounded-full px-2 py-1"><?php echo $progress; ?>%</div>
                                                </div>

                                                <div class="text-center text-sm font-semibold text-gray-700 mb-3">Faltante para Meta</div>
                                                <div class="grid grid-cols-3 gap-3 text-center">
                                                    <div class="bg-gray-50 p-2 rounded-lg">
                                                        <div class="text-xs text-gray-500">Tarimas</div>
                                                        <div id="remaining-pallets-<?php echo $product['id']; ?>" class="font-bold"><?php echo $remainingData['fullPallets']; ?></div>
                                                    </div>
                                                    <div class="bg-gray-50 p-2 rounded-lg">
                                                        <div class="text-xs text-gray-500">Cajas</div>
                                                        <div id="remaining-boxes-<?php echo $product['id']; ?>" class="font-bold"><?php echo $remainingData['remainingBoxes']; ?></div>
                                                    </div>
                                                    <div class="bg-gray-50 p-2 rounded-lg">
                                                        <div class="text-xs text-gray-500">Total Cajas</div>
                                                        <div id="remaining-total-<?php echo $product['id']; ?>" class="font-bold"><?php echo $remainingBoxes; ?></div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="bg-green-100 p-4 rounded-lg shadow-sm text-center">
                                                <div class="flex justify-center items-center">
                                                    <i class="fas fa-check-circle text-green-500 text-xl mr-2"></i>
                                                    <span class="font-bold text-green-700">¡META ALCANZADA!</span>
                                                </div>
                                                <div class="mt-2 text-sm text-green-600">Se han producido <?php echo $totalBoxes; ?> cajas</div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Alertas y control de producción -->
                                    <div class="flex flex-col gap-2">
                                        <?php if (!$isComplete): ?>
                                            <?php if ($closeToGoal): ?>
                                                <div class="flex items-center p-3 bg-yellow-100 text-yellow-800 rounded-lg text-sm">
                                                    <i class="fas fa-exclamation-circle mr-2 text-yellow-600"></i>
                                                    <span>Faltan 3 tarimas o menos para la meta</span>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!$continues): ?>
                                                <div class="flex items-center p-3 bg-red-100 text-red-800 rounded-lg text-sm">
                                                    <i class="fas fa-exclamation-triangle mr-2 text-red-600"></i>
                                                    <span>NO continúa en el siguiente turno</span>
                                                </div>
                                            <?php else: ?>
                                                <div class="flex items-center p-3 bg-green-100 text-green-800 rounded-lg text-sm">
                                                    <i class="fas fa-check-circle mr-2 text-green-600"></i>
                                                    <span>Continúa en el siguiente turno</span>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if ($mode === EDIT_MODE && !$isComplete): ?>
                                            <!-- Actualizar Producción -->
                                            <div class="mt-2 p-4 bg-gray-50 rounded-lg">
                                                <div class="grid grid-cols-3 gap-3 mb-3">
                                                    <button 
                                                        onclick="updateProduction(<?php echo $product['id']; ?>, '<?php echo $currentShift; ?>', 1)"
                                                        class="bg-white hover:bg-gray-100 text-gray-800 border border-gray-200 py-2 px-3 rounded-lg text-sm shadow-sm font-medium transition-colors action-button"
                                                    >
                                                        +1
                                                    </button>
                                                    <button 
                                                        onclick="updateProduction(<?php echo $product['id']; ?>, '<?php echo $currentShift; ?>', 10)"
                                                        class="bg-white hover:bg-gray-100 text-gray-800 border border-gray-200 py-2 px-3 rounded-lg text-sm shadow-sm font-medium transition-colors action-button"
                                                    >
                                                        +10
                                                    </button>
                                                    <button 
                                                        onclick="updateProduction(<?php echo $product['id']; ?>, '<?php echo $currentShift; ?>', <?php echo $config['boxes_per_pallet']; ?>)"
                                                        class="bg-white hover:bg-gray-100 text-gray-800 border border-gray-200 py-2 px-3 rounded-lg text-sm shadow-sm font-medium transition-colors action-button"
                                                    >
                                                        +<?php echo $config['boxes_per_pallet']; ?>
                                                    </button>
                                                </div>

                                                <button 
                                                    onclick="openExactProductionModal(<?php echo $product['id']; ?>, '<?php echo $currentShift; ?>', <?php echo $totalBoxes; ?>)"
                                                    class="w-full py-2 px-3 gradient-button rounded-lg text-sm font-medium transition-colors flex items-center justify-center action-button"
                                                >
                                                    <i class="fas fa-edit mr-1"></i>
                                                    Modificar producción exacta
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full bg-white p-8 rounded-xl shadow-sm text-center">
                        <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHBhdGggZD0iTTEwMCAzMEM2MS4zNCAzMCAzMCA2MS4zNCAzMCAxMDBDMzAgMTM4LjY2IDYxLjM0IDE3MCAxMDAgMTcwQzEzOC42NiAxNzAgMTcwIDEzOC42NiAxNzAgMTAwQzE3MCA2MS4zNCAxMzguNjYgMzAgMTAwIDMwWk0xMDAgMTUwQzg5LjIgMTUwIDgwIDE0MC44IDgwIDEzMEM4MCAxMTkuMiA4OS4yIDExMCAxMDAgMTEwQzExMC44IDExMCAxMjAgMTE5LjIgMTIwIDEzMEMxMjAgMTQwLjggMTEwLjggMTUwIDEwMCAxNTBaTTEwNSA0MEg5NUM5MC42IDQwIDg3IDQzLjYgODcgNDhMOTMgOThDOTMuNiAxMDEuOCA5Ni44IDEwNSAxMDAgMTA1QzEwMy4yIDEwNSAxMDYuNCAxMDEuOCAxMDcgOThMMTEzIDQ4QzExMyA0My42IDEwOS40IDQwIDEwNSA0MFoiIGZpbGw9IiNFOUVERjIiLz48L3N2Zz4=" alt="No productos" class="mx-auto mb-4 w-24 h-24 opacity-50">
                        <div class="text-gray-500 mb-4">No hay productos para mostrar</div>
                        <div class="text-sm text-gray-400 mb-6">Prueba con diferentes filtros o añade un nuevo producto</div>
                        <?php if ($mode === EDIT_MODE): ?>
                            <button 
                                id="emptyAddProductButton"
                                class="gradient-button px-5 py-3 rounded-lg flex items-center gap-2 mx-auto shadow-sm transition-colors"
                            >
                                <i class="fas fa-plus-circle"></i>
                                <span>Agregar Producto</span>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sonidos de notificación -->
    <audio id="notificationSuccessSound" preload="auto">
    <source src="https://assets.mixkit.co/sfx/preview/mixkit-positive-notification-951.mp3" type="audio/mpeg">
    </audio>
    <audio id="notificationWarningSound" preload="auto">
    <source src="https://assets.mixkit.co/sfx/preview/mixkit-software-interface-start-2574.mp3" type="audio/mpeg">
    </audio>
    <audio id="notificationErrorSound" preload="auto">
    <source src="https://assets.mixkit.co/sfx/preview/mixkit-wrong-answer-fail-notification-946.mp3" type="audio/mpeg">
    </audio>

    <?php if ($mode === EDIT_MODE): ?>
    <!-- Modal para agregar/editar producto -->
    <div id="productModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-xl md:max-w-2xl">
            <div class="flex justify-between items-center mb-5">
                <h2 class="text-xl font-bold text-gray-800" id="modalTitle">Agregar Nuevo Producto</h2>
                <button onclick="closeProductModal()" class="text-gray-500 hover:text-gray-700 transition-colors p-2 bg-gray-100 rounded-full">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form id="productForm" method="POST" action="">
                <input type="hidden" name="action" value="saveProduct">
                <input type="hidden" id="productData" name="productData" value="">
                <input type="hidden" name="current_shift" value="<?php echo htmlspecialchars($shiftFilter); ?>">
                <input type="hidden" name="current_search" value="<?php echo htmlspecialchars($searchTerm); ?>">
                <input type="hidden" name="current_filter" value="<?php echo htmlspecialchars($statusFilter); ?>">

                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del Producto</label>
                        <input
                            type="text"
                            id="productName"
                            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            required
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-3">Configuración por Turnos</label>

                        <!-- Turno Mañana -->
                        <div class="border border-gray-200 rounded-xl p-4 mb-3 shadow-sm">
                            <div class="flex items-center justify-between mb-2">
                                <label class="flex items-center gap-2 font-medium">
                                    <input
                                        type="checkbox"
                                        id="morningActive"
                                        class="h-4 w-4 text-indigo-600 rounded focus:ring-indigo-500 border-gray-300"
                                    >
                                    <span class="flex items-center text-blue-600">
                                        <i class="fas fa-sun mr-1"></i>
                                        Turno Mañana
                                    </span>
                                </label>
                            </div>

                            <div id="morningSettings" class="hidden pl-6 border-l-2 border-blue-200">
                                <label class="block text-sm text-gray-700 mb-1">Meta de Producción (cajas)</label>
                                <input
                                    type="number"
                                    id="morningGoal"
                                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    min=""
                                >
                            </div>
                        </div>

                        <!-- Turno Tarde -->
                        <div class="border border-gray-200 rounded-xl p-4 mb-3 shadow-sm">
                            <div class="flex items-center justify-between mb-2">
                                <label class="flex items-center gap-2 font-medium">
                                    <input
                                        type="checkbox"
                                        id="afternoonActive"
                                        class="h-4 w-4 text-indigo-600 rounded focus:ring-indigo-500 border-gray-300"
                                    >
                                    <span class="flex items-center text-orange-600">
                                        <i class="fas fa-cloud-sun mr-1"></i>
                                        Turno Tarde
                                    </span>
                                </label>
                            </div>

                            <div id="afternoonSettings" class="hidden pl-6 border-l-2 border-orange-200">
                                <label class="block text-sm text-gray-700 mb-1">Meta de Producción (cajas)</label>
                                <input
                                    type="number"
                                    id="afternoonGoal"
                                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                    min=""
                                >
                            </div>
                        </div>

                        <!-- Turno Noche -->
                        <div class="border border-gray-200 rounded-xl p-4 shadow-sm">
                            <div class="flex items-center justify-between mb-2">
                                <label class="flex items-center gap-2 font-medium">
                                    <input
                                        type="checkbox"
                                        id="nightActive"
                                        class="h-4 w-4 text-indigo-600 rounded focus:ring-indigo-500 border-gray-300"
                                    >
                                    <span class="flex items-center text-indigo-600">
                                        <i class="fas fa-moon mr-1"></i>
                                        Turno Noche
                                    </span>
                                </label>
                            </div>

                            <div id="nightSettings" class="hidden pl-6 border-l-2 border-indigo-200">
                                <label class="block text-sm text-gray-700 mb-1">Meta de Producción (cajas)</label>
                                <input
                                    type="number"
                                    id="nightGoal"
                                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                    min=""
                                >
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4">
                        <button
                            type="button"
                            onclick="closeProductModal()"
                            class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg mr-2 transition-colors"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="gradient-button px-6 py-2 rounded-lg flex items-center gap-2 transition-colors"
                        >
                            <i class="fas fa-save"></i>
                            <span>Guardar</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para modificar producción -->
    <div id="productionModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-5">
                <h2 class="text-xl font-bold text-gray-800">Modificar Producción</h2>
                <button onclick="closeExactProductionModal()" class="text-gray-500 hover:text-gray-700 transition-colors p-2 bg-gray-100 rounded-full">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Producción Actual (cajas totales)
                    </label>
                    <input
                        type="number"
                        id="exactBoxes"
                        class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        min="0"
                        placeholder="Ingrese el número de cajas"
                        required
                    >
                </div>

                <div class="flex justify-end pt-4">
                    <button
                        type="button"
                        onclick="closeExactProductionModal()"
                        class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg mr-2 transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        onclick="submitExactProduction()"
                        class="gradient-button px-6 py-2 rounded-lg flex items-center gap-2 transition-colors"
                    >
                        <i class="fas fa-save"></i>
                        <span>Guardar</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulario para eliminar producto -->
    <form id="deleteProductForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="deleteProduct">
        <input type="hidden" id="deleteProductId" name="productId" value="">
        <input type="hidden" name="current_shift" value="<?php echo htmlspecialchars($shiftFilter); ?>">
        <input type="hidden" name="current_search" value="<?php echo htmlspecialchars($searchTerm); ?>">
        <input type="hidden" name="current_filter" value="<?php echo htmlspecialchars($statusFilter); ?>">
    </form>
    <?php endif; ?>

    <script>
    // Configuración desde PHP para JavaScript
    const notifyOnComplete = <?php echo isset($config['notify_on_complete']) && $config['notify_on_complete'] ? 'true' : 'false'; ?>;
    const notificationSound = <?php echo isset($config['notification_sound']) && $config['notification_sound'] ? 'true' : 'false'; ?>;

    // Función para manejar el panel de notificaciones
    document.getElementById('notificationButton').addEventListener('click', function() {
        const panel = document.getElementById('notificationPanel');
        panel.classList.toggle('hidden');
    });

    // Cerrar el panel de notificaciones cuando se hace clic fuera de él
    document.addEventListener('click', function(event) {
        const panel = document.getElementById('notificationPanel');
        const button = document.getElementById('notificationButton');

        if (!panel.contains(event.target) && !button.contains(event.target) && !panel.classList.contains('hidden')) {
            panel.classList.add('hidden');
        }
    });

    // Marcar notificación como leída
    document.querySelectorAll('.mark-read-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            const notificationId = this.getAttribute('data-id');
            markNotificationRead(notificationId);
        });
    });

    // Marcar todas las notificaciones como leídas
    document.getElementById('markAllReadBtn')?.addEventListener('click', function(e) {
        e.stopPropagation();
        markAllNotificationsRead();
    });

    // Borrar todas las notificaciones
    document.getElementById('clearAllNotificationsBtn')?.addEventListener('click', function(e) {
        e.stopPropagation();
        clearAllNotifications();
    });

    // Funciones para gestionar notificaciones mediante AJAX
    function markNotificationRead(notificationId) {
        const formData = new FormData();
        formData.append('ajax', 'true');
        formData.append('action', 'markNotificationRead');
        formData.append('notificationId', notificationId);

        fetch('index.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Marcar visualmente como leída
                const notificationItem = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
                if (notificationItem) {
                    notificationItem.classList.remove('bg-blue-50');
                    notificationItem.classList.add('bg-white');

                    const markReadBtn = notificationItem.querySelector('.mark-read-btn');
                    if (markReadBtn) {
                        markReadBtn.remove();
                    }
                }

                // Actualizar contador de notificaciones
                updateUnreadCount();
            }
        })
        .catch(error => console.error('Error:', error));
    }

    function markAllNotificationsRead() {
        const formData = new FormData();
        formData.append('ajax', 'true');
        formData.append('action', 'markAllNotificationsRead');

        fetch('index.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Marcar visualmente todas como leídas
                document.querySelectorAll('.notification-item').forEach(item => {
                    item.classList.remove('bg-blue-50');
                    item.classList.add('bg-white');

                    const markReadBtn = item.querySelector('.mark-read-btn');
                    if (markReadBtn) {
                        markReadBtn.remove();
                    }
                });

                // Actualizar contador de notificaciones
                const badge = document.querySelector('.notification-badge');
                if (badge) {
                    badge.remove();
                }
            }
        })
        .catch(error => console.error('Error:', error));
    }

    function clearAllNotifications() {
        const formData = new FormData();
        formData.append('ajax', 'true');
        formData.append('action', 'clearAllNotifications');

        fetch('index.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Limpiar panel de notificaciones
                const container = document.getElementById('notificationContainer');
                if (container) {
                    container.innerHTML = `
                    <div class="p-8 text-center text-gray-500">
                        <i class="fas fa-bell-slash text-4xl mb-3 opacity-30"></i>
                        <p>No hay notificaciones</p>
                    </div>
                    `;
                }

                // Actualizar contador de notificaciones
                const badge = document.querySelector('.notification-badge');
                if (badge) {
                    badge.remove();
                }
            }
        })
        .catch(error => console.error('Error:', error));
    }

    function updateUnreadCount() {
        // Contar notificaciones no leídas
        const unreadItems = document.querySelectorAll('.notification-item.bg-blue-50').length;
        const badge = document.querySelector('.notification-badge');

        if (unreadItems === 0 && badge) {
            badge.remove();
        } else if (unreadItems > 0) {
            if (badge) {
                badge.textContent = unreadItems;
            } else {
                const newBadge = document.createElement('span');
                newBadge.className = 'notification-badge pulse-animation';
                newBadge.textContent = unreadItems;
                document.getElementById('notificationButton').appendChild(newBadge);
            }
        }
    }

    // Manejar clics en botones de favoritos
    document.querySelectorAll('.favorite-button').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-id');
            toggleFavorite(productId, this);
        });
    });

    function toggleFavorite(productId, buttonElement) {
        const formData = new FormData();
        formData.append('ajax', 'true');
        formData.append('action', 'toggleFavorite');
        formData.append('productId', productId);

        fetch('index.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.isFavorite) {
                    buttonElement.classList.add('favorite-active');
                    buttonElement.classList.remove('text-gray-300', 'hover:text-gray-500');
                    buttonElement.querySelector('.tooltip-text').textContent = 'Quitar de favoritos';
                    showToast(`Producto añadido a favoritos`, 'info');
                } else {
                    buttonElement.classList.remove('favorite-active');
                    buttonElement.classList.add('text-gray-300', 'hover:text-gray-500');
                    buttonElement.querySelector('.tooltip-text').textContent = 'Añadir a favoritos';
                    showToast(`Producto eliminado de favoritos`, 'info');
                }
            }
        })
        .catch(error => console.error('Error:', error));
    }

    // Función para aplicar el efecto de destello verde a un elemento
    function applyGreenFlash(element) {
        // Primero eliminamos la clase si ya existe
        element.classList.remove('green-flash');

        // Forzamos un reflow para reiniciar la animación
        void element.offsetWidth;

        // Luego aplicamos la clase de animación
        element.classList.add('green-flash');
    }

    // Función para mostrar un toast
    function showToast(message, type = 'success', duration = 5000) {
        const toastContainer = document.getElementById('toastContainer');

        // Crear el elemento toast
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;

        // Elegir el ícono según el tipo
        let icon = 'check-circle';
        let colorClass = 'text-green-500';

        if (type === 'warning') {
            icon = 'exclamation-circle';
            colorClass = 'text-yellow-500';
        } else if (type === 'error') {
            icon = 'exclamation-triangle';
            colorClass = 'text-red-500';
        } else if (type === 'info') {
            icon = 'info-circle';
            colorClass = 'text-blue-500';
        }

        toast.innerHTML = `
        <div class="toast-icon ${colorClass}">
            <i class="fas fa-${icon}"></i>
        </div>
        <div class="toast-content">
            <div class="font-bold text-gray-800">${message}</div>
        </div>
        `;

        // Agregar al contenedor
        toastContainer.appendChild(toast);

        // Mostrar con animación
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);

        // Reproducir sonido si está habilitado
        if (notificationSound) {
            let audio;

            if (type === 'success') {
                audio = document.getElementById('notificationSuccessSound');
            } else if (type === 'warning') {
                audio = document.getElementById('notificationWarningSound');
            } else if (type === 'error') {
                audio = document.getElementById('notificationErrorSound');
            } else {
                audio = document.getElementById('notificationSuccessSound');
            }

            audio?.play().catch(e => console.error('Error al reproducir sonido:', e));
        }

        // Ocultar después de la duración
        setTimeout(() => {
            toast.classList.add('hide');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, duration);
    }

    // Función para aplicar efecto de celebración a un producto
    function applyCelebrationEffect(productId) {
        const productCard = document.getElementById(`product-${productId}`);
        if (productCard) {
            // Añadir clase de borde si no existe
            if (!productCard.classList.contains('border-l-4')) {
                productCard.classList.add('border-l-4', 'border-green-500');
            } else {
                // Cambiar color del borde a verde
                productCard.classList.remove('border-red-500', 'border-yellow-500');
                productCard.classList.add('border-green-500');
            }

            // Quitar la animación de advertencia si existe
            productCard.classList.remove('warning-animation');

            // Añadir efecto de celebración
            productCard.classList.add('celebration-animation');
        }
    }

    // Función para aplicar efecto de proximidad a la meta
    function applyCloseToGoalEffect(productId) {
        const productCard = document.getElementById(`product-${productId}`);
        if (productCard) {
            // Añadir clase de borde si no existe
            if (!productCard.classList.contains('border-l-4')) {
                productCard.classList.add('border-l-4', 'border-yellow-500');
            } else if (!productCard.classList.contains('border-green-500')) {
                // Cambiar color del borde a amarillo si no está completado
                productCard.classList.remove('border-red-500');
                productCard.classList.add('border-yellow-500');
            }

            // Añadir efecto de parpadeo naranja si no está completo
            if (!productCard.classList.contains('celebration-animation')) {
                productCard.classList.add('warning-animation');
            }
        }
    }

    // Función mejorada para actualizar la UI del resumen de progreso por turno
    function updateShiftProgressSummary(summary) {
        if (!summary) return;
        
        console.log("Actualizando resumen por turno:", summary);
        
        // Verificar si estamos en vista de 'Todos los turnos'
        const isAllShiftsView = <?php echo $shiftFilter === 'all' ? 'true' : 'false'; ?>;
        
        if (isAllShiftsView) {
            // Actualizar los valores en la vista de 'Todos los turnos'
            
            // Turno Mañana
            const morningProgressValue = document.getElementById('morning-progress-value');
            if (morningProgressValue && summary.morning_progress !== undefined) {
                morningProgressValue.style.width = `${summary.morning_progress}%`;
            }
            
            const morningProgressPercent = document.getElementById('morning-progress-percent');
            if (morningProgressPercent && summary.morning_progress !== undefined) {
                morningProgressPercent.textContent = `${summary.morning_progress}% Completado`;
            }
            
            const morningPalletsProduced = document.getElementById('morning-pallets-produced');
            if (morningPalletsProduced && summary.morning_pallets_produced !== undefined) {
                morningPalletsProduced.textContent = summary.morning_pallets_produced;
            }
            
            const morningPalletsRemaining = document.getElementById('morning-pallets-remaining');
            if (morningPalletsRemaining && summary.morning_pallets_remaining !== undefined) {
                morningPalletsRemaining.textContent = summary.morning_pallets_remaining;
            }
            
            // Turno Tarde
            const afternoonProgressValue = document.getElementById('afternoon-progress-value');
            if (afternoonProgressValue && summary.afternoon_progress !== undefined) {
                afternoonProgressValue.style.width = `${summary.afternoon_progress}%`;
            }
            
            const afternoonProgressPercent = document.getElementById('afternoon-progress-percent');
            if (afternoonProgressPercent && summary.afternoon_progress !== undefined) {
                afternoonProgressPercent.textContent = `${summary.afternoon_progress}% Completado`;
            }
            
            const afternoonPalletsProduced = document.getElementById('afternoon-pallets-produced');
            if (afternoonPalletsProduced && summary.afternoon_pallets_produced !== undefined) {
                afternoonPalletsProduced.textContent = summary.afternoon_pallets_produced;
            }
            
            const afternoonPalletsRemaining = document.getElementById('afternoon-pallets-remaining');
            if (afternoonPalletsRemaining && summary.afternoon_pallets_remaining !== undefined) {
                afternoonPalletsRemaining.textContent = summary.afternoon_pallets_remaining;
            }
            
            // Turno Noche
            const nightProgressValue = document.getElementById('night-progress-value');
            if (nightProgressValue && summary.night_progress !== undefined) {
                nightProgressValue.style.width = `${summary.night_progress}%`;
            }
            
            const nightProgressPercent = document.getElementById('night-progress-percent');
            if (nightProgressPercent && summary.night_progress !== undefined) {
                nightProgressPercent.textContent = `${summary.night_progress}% Completado`;
            }
            
            const nightPalletsProduced = document.getElementById('night-pallets-produced');
            if (nightPalletsProduced && summary.night_pallets_produced !== undefined) {
                nightPalletsProduced.textContent = summary.night_pallets_produced;
            }
            
            const nightPalletsRemaining = document.getElementById('night-pallets-remaining');
            if (nightPalletsRemaining && summary.night_pallets_remaining !== undefined) {
                nightPalletsRemaining.textContent = summary.night_pallets_remaining;
            }
        } else {
            // Actualizar la vista de turno específico
            
            // TURNO MAÑANA
            // Actualizar barra de progreso mañana
            const morningProgressBar = document.getElementById('morning-progress-bar');
            if (morningProgressBar) {
                morningProgressBar.style.width = `${summary.morning_progress}%`;
            }

            // Actualizar texto de porcentaje mañana
            const morningProgressText = document.getElementById('morning-progress-text');
            if (morningProgressText) {
                morningProgressText.innerHTML = `${summary.morning_progress}%`;
            }

            // Actualizar texto de tarimas mañana
            const morningPalletsText = document.getElementById('morning-pallets-summary');
            if (morningPalletsText) {
                morningPalletsText.innerHTML = `${summary.morning_pallets_produced} de ${summary.morning_pallets_produced + summary.morning_pallets_remaining} tarimas`;
            }

            // TURNO TARDE
            // Actualizar barra de progreso tarde
            const afternoonProgressBar = document.getElementById('afternoon-progress-bar');
            if (afternoonProgressBar) {
                afternoonProgressBar.style.width = `${summary.afternoon_progress}%`;
            }

            // Actualizar texto de porcentaje tarde
            const afternoonProgressText = document.getElementById('afternoon-progress-text');
            if (afternoonProgressText) {
                afternoonProgressText.innerHTML = `${summary.afternoon_progress}%`;
            }

            // Actualizar texto de tarimas tarde
            const afternoonPalletsText = document.getElementById('afternoon-pallets-summary');
            if (afternoonPalletsText) {
                afternoonPalletsText.innerHTML = `${summary.afternoon_pallets_produced} de ${summary.afternoon_pallets_produced + summary.afternoon_pallets_remaining} tarimas`;
            }

            // TURNO NOCHE
            // Actualizar barra de progreso noche
            const nightProgressBar = document.getElementById('night-progress-bar');
            if (nightProgressBar) {
                nightProgressBar.style.width = `${summary.night_progress}%`;
            }

            // Actualizar texto de porcentaje noche
            const nightProgressText = document.getElementById('night-progress-text');
            if (nightProgressText) {
                nightProgressText.innerHTML = `${summary.night_progress}%`;
            }

            // Actualizar texto de tarimas noche
            const nightPalletsText = document.getElementById('night-pallets-summary');
            if (nightPalletsText) {
                nightPalletsText.innerHTML = `${summary.night_pallets_produced} de ${summary.night_pallets_produced + summary.night_pallets_remaining} tarimas`;
            }
        }
    }

    <?php if ($mode === EDIT_MODE): ?>
    // Variables para el formulario de productos
    let currentProductId = null;
    let savedScroll = 0;

    // Variables para producción exacta
    let currentExactProductId = null;
    let currentExactShift = null;

    // Event listeners para botones de agregar producto
    document.getElementById('addProductButton')?.addEventListener('click', openProductModal);
    document.getElementById('quickAddProductBtn')?.addEventListener('click', openProductModal);
    document.getElementById('emptyAddProductButton')?.addEventListener('click', openProductModal);

    // Event listeners para cambio en estado de turnos
    document.getElementById('morningActive')?.addEventListener('change', function() {
        document.getElementById('morningSettings').style.display = this.checked ? 'block' : 'none';
    });

    document.getElementById('afternoonActive')?.addEventListener('change', function() {
        document.getElementById('afternoonSettings').style.display = this.checked ? 'block' : 'none';
    });

    document.getElementById('nightActive')?.addEventListener('change', function() {
        document.getElementById('nightSettings').style.display = this.checked ? 'block' : 'none';
    });

    // Event listener para el formulario de producto
    document.getElementById('productForm')?.addEventListener('submit', function(e) {
        e.preventDefault();

        // Crear objeto con datos del producto
        const productData = {
            name: document.getElementById('productName').value,
            shifts: {
                morning: {
                    active: document.getElementById('morningActive').checked,
                    productionGoal: parseInt(document.getElementById('morningGoal').value || 0),
                    currentProduction: 0,
                    pallets: 0,
                    boxes: 0
                },
                afternoon: {
                    active: document.getElementById('afternoonActive').checked,
                    productionGoal: parseInt(document.getElementById('afternoonGoal').value || 0),
                    currentProduction: 0,
                    pallets: 0,
                    boxes: 0
                },
                night: {
                    active: document.getElementById('nightActive').checked,
                    productionGoal: parseInt(document.getElementById('nightGoal').value || 0),
                    currentProduction: 0,
                    pallets: 0,
                    boxes: 0
                }
            }
        };

        // Si es edición, incluir id e información de producción actual
        if (currentProductId) {
            // Buscar el producto actual en la lista de productos
            const productJson = productOriginalData;

            if (productJson) {
                // Preservar datos de producción actuales
                productData.id = currentProductId;
                productData.created_at = productJson.created_at || 0;

                if (productJson.shifts && productJson.shifts.morning) {
                    productData.shifts.morning.currentProduction = productJson.shifts.morning.currentProduction || 0;
                    productData.shifts.morning.pallets = productJson.shifts.morning.pallets || 0;
                    productData.shifts.morning.boxes = productJson.shifts.morning.boxes || 0;
                }
                if (productJson.shifts && productJson.shifts.afternoon) {
                    productData.shifts.afternoon.currentProduction = productJson.shifts.afternoon.currentProduction || 0;
                    productData.shifts.afternoon.pallets = productJson.shifts.afternoon.pallets || 0;
                    productData.shifts.afternoon.boxes = productJson.shifts.afternoon.boxes || 0;
                }
                if (productJson.shifts && productJson.shifts.night) {
                    productData.shifts.night.currentProduction = productJson.shifts.night.currentProduction || 0;
                    productData.shifts.night.pallets = productJson.shifts.night.pallets || 0;
                    productData.shifts.night.boxes = productJson.shifts.night.boxes || 0;
                }
            } else {
                productData.id = currentProductId;
            }
        }

        // Convertir a JSON y enviar
        document.getElementById('productData').value = JSON.stringify(productData);
        this.submit();
    });

    // Variable para almacenar los datos originales del producto en edición
    let productOriginalData = null;

    // Función para abrir el modal de producto
    function openProductModal() {
        document.getElementById('modalTitle').textContent = 'Agregar Nuevo Producto';
        document.getElementById('productName').value = '';
        document.getElementById('morningActive').checked = false;
        document.getElementById('afternoonActive').checked = false;
        document.getElementById('nightActive').checked = false;
        document.getElementById('morningGoal').value = '';
        document.getElementById('afternoonGoal').value = '';
        document.getElementById('nightGoal').value = '';
        document.getElementById('morningSettings').style.display = 'none';
        document.getElementById('afternoonSettings').style.display = 'none';
        document.getElementById('nightSettings').style.display = 'none';

        currentProductId = null;
        productOriginalData = null;
        document.getElementById('productModal').classList.remove('hidden');
    }

    // Función para editar un producto
    function editProduct(product) {
        document.getElementById('modalTitle').textContent = 'Editar Producto';
        document.getElementById('productName').value = product.name;

        // Guardar datos originales del producto
        productOriginalData = product;

        // Configurar turnos
        document.getElementById('morningActive').checked = product.shifts.morning.active;
        document.getElementById('afternoonActive').checked = product.shifts.afternoon.active;
        document.getElementById('nightActive').checked = product.shifts.night.active;

        document.getElementById('morningGoal').value = product.shifts.morning.productionGoal || '';
        document.getElementById('afternoonGoal').value = product.shifts.afternoon.productionGoal || '';
        document.getElementById('nightGoal').value = product.shifts.night.productionGoal || '';

        document.getElementById('morningSettings').style.display = product.shifts.morning.active ? 'block' : 'none';
        document.getElementById('afternoonSettings').style.display = product.shifts.afternoon.active ? 'block' : 'none';
        document.getElementById('nightSettings').style.display = product.shifts.night.active ? 'block' : 'none';

        currentProductId = product.id;
        document.getElementById('productModal').classList.remove('hidden');
    }

    // Función para cerrar el modal de producto
    function closeProductModal() {
        document.getElementById('productModal').classList.add('hidden');
    }

    // Función para actualizar la producción mediante AJAX
    function updateProduction(productId, shift, boxes) {
        const formData = new FormData();
        formData.append('ajax', 'true');
        formData.append('action', 'updateProduction');
        formData.append('productId', productId);
        formData.append('shift', shift);
        formData.append('boxes', boxes);

        fetch('index.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Actualizar los valores en la interfaz
                updateProductionUI(productId, shift, data.data);

                // Actualizar el resumen de progreso por turno si existe
                if (data.summary) {
                    updateShiftProgressSummary(data.summary);
                }

                // Aplicar efecto de destello verde a la tarjeta completa
                const productCard = document.getElementById(`product-${productId}`);
                if (productCard) {
                    applyGreenFlash(productCard);
                }

                // Si completó la meta, mostrar notificación y aplicar efecto
                if (data.data.completed) {
                    applyCelebrationEffect(productId);
                    showToast(`¡${data.data.productName} ha alcanzado su meta de producción!`, 'success');

                    // Actualizar estilo del turno a completado
                    updateShiftCompletedUI(productId, shift);

                    // Actualizar contenido de la tarjeta para mostrar el mensaje de meta alcanzada
                    showCompletedUI(productId);
                }
                // Si está cerca de la meta, mostrar notificación
                else if (data.data.closeToGoal) {
                    applyCloseToGoalEffect(productId);
                    showToast(`¡${data.data.productName} está a 3 tarimas o menos de la meta!`, 'warning');
                }

                // Mostrar mensaje en la interfaz
                showToast(data.message, 'info');
            }
        })
        .catch(error => console.error('Error:', error));
    }

    // Función para actualizar el estilo del turno a completado
    function updateShiftCompletedUI(productId, shift) {
        const productCard = document.getElementById(`product-${productId}`);
        if (productCard) {
            // Obtener el índice del turno para seleccionar el elemento correcto
            const shiftIndex = shift === 'morning' ? 0 : (shift === 'afternoon' ? 1 : 2);

            // Buscar el turno 
            const shiftElements = productCard.querySelectorAll('.bg-gray-50 .flex.flex-wrap.justify-center.gap-3 > div');
            if (shiftElements && shiftElements.length > shiftIndex) {
                const shiftElement = shiftElements[shiftIndex];

                // Actualizar el estilo
                if (!shiftElement.classList.contains('bg-blue-500')) { // Si no es el turno actual
                    shiftElement.className = 'text-center p-2 rounded-lg shadow-sm bg-red-500 text-white';

                    // Verificar si ya existe el indicador de completado
                    if (!shiftElement.querySelector('.mt-1.text-xs.font-semibold.bg-white.bg-opacity-20')) {
                        const statusElement = document.createElement('div');
                        statusElement.className = 'mt-1 text-xs font-semibold bg-white bg-opacity-20 rounded-full p-1';
                        statusElement.textContent = 'Completado';
                        shiftElement.appendChild(statusElement);
                    }
                }
            }
        }
    }

    // Función para mostrar la interfaz de meta alcanzada
    function showCompletedUI(productId) {
        const productCard = document.getElementById(`product-${productId}`);
        if (productCard) {
            // Buscar la sección de información de lo que falta
            const remainingSection = productCard.querySelector('.bg-white.p-4.rounded-lg.shadow-sm:not(.text-center)');
            if (remainingSection) {
                // Reemplazar con mensaje de meta alcanzada
                remainingSection.className = 'bg-green-100 p-4 rounded-lg shadow-sm text-center';
                remainingSection.innerHTML = `
                <div class="flex justify-center items-center">
                    <i class="fas fa-check-circle text-green-500 text-xl mr-2"></i>
                    <span class="font-bold text-green-700">¡META ALCANZADA!</span>
                </div>
                <div class="mt-2 text-sm text-green-600">Se han producido ${productCard.querySelector('#total-boxes-' + productId + '-morning, #total-boxes-' + productId + '-afternoon, #total-boxes-' + productId + '-night').textContent}</div>
                `;
            }

            // Ocultar alertas y controles de producción
            const alertsSection = productCard.querySelector('.flex.flex-col.gap-2');
            if (alertsSection) {
                // Limpiar alertas
                alertsSection.innerHTML = '';
            }
        }
    }

    // Función para actualizar la UI después de actualizar producción
    function updateProductionUI(productId, shift, data) {
        // Actualizar total de cajas
        const totalBoxesElement = document.getElementById(`total-boxes-${productId}-${shift}`);
        if (totalBoxesElement) {
            totalBoxesElement.textContent = `${data.totalBoxes} cajas`;
        }

        // Actualizar tarimas
        const palletsElement = document.getElementById(`pallets-${productId}-${shift}`);
        if (palletsElement) {
            palletsElement.textContent = data.pallets;
        }

        // Actualizar cajas
        const boxesElement = document.getElementById(`boxes-${productId}-${shift}`);
        if (boxesElement) {
            boxesElement.textContent = data.boxes;
        }

        // Actualizar hora de actualización
        const lastUpdateElement = document.getElementById(`last-update-${productId}`);
        if (lastUpdateElement) {
            lastUpdateElement.textContent = data.lastUpdate;
        }

        // Actualizar barra de progreso
        const progressBarElements = document.querySelectorAll(`#product-${productId} .bg-white.p-4.rounded-lg.shadow-sm:not(.text-center) .flex.items-center.mb-2 .h-2.flex-grow .h-2`);
        if (progressBarElements.length > 0) {
            progressBarElements[0].className = `h-2 ${data.progressColor} rounded-full`;
            progressBarElements[0].style.width = `${data.progress}%`;

            // Actualizar porcentaje
            const progressTextElements = document.querySelectorAll(`#product-${productId} .bg-white.p-4.rounded-lg.shadow-sm:not(.text-center) .flex.items-center.mb-2 .ml-3.text-sm.font-bold`);
            if (progressTextElements.length > 0) {
                progressTextElements[0].textContent = `${data.progress}%`;
            }
        }

        // Actualizar datos restantes
        const remainingPalletsElement = document.getElementById(`remaining-pallets-${productId}`);
        if (remainingPalletsElement) {
            remainingPalletsElement.textContent = data.remainingPallets;
        }

        const remainingBoxesElement = document.getElementById(`remaining-boxes-${productId}`);
        if (remainingBoxesElement) {
            remainingBoxesElement.textContent = data.remainingBoxes;
        }

        const remainingTotalElement = document.getElementById(`remaining-total-${productId}`);
        if (remainingTotalElement) {
            remainingTotalElement.textContent = data.remainingTotal;
        }

        // Actualizar el ícono de favorito si es necesario
        if (data.isFavorite !== undefined) {
            const favoriteButton = document.querySelector(`.favorite-button[data-id="${productId}"]`);
            if (favoriteButton) {
                if (data.isFavorite) {
                    favoriteButton.classList.add('favorite-active');
                    favoriteButton.classList.remove('text-gray-300', 'hover:text-gray-500');
                } else {
                    favoriteButton.classList.remove('favorite-active');
                    favoriteButton.classList.add('text-gray-300', 'hover:text-gray-500');
                }
            }
        }
    }

    // Función para abrir el modal de producción exacta
    function openExactProductionModal(productId, shift, currentValue) {
        currentExactProductId = productId;
        currentExactShift = shift;
        document.getElementById('exactBoxes').value = currentValue > 0 ? currentValue : '';
        document.getElementById('productionModal').classList.remove('hidden');
    }

    // Función para cerrar el modal de producción exacta
    function closeExactProductionModal() {
        document.getElementById('productionModal').classList.add('hidden');
        currentExactProductId = null;
        currentExactShift = null;
    }

    // Función para enviar cambio de producción exacta mediante AJAX
    function submitExactProduction() {
        const exactBoxes = document.getElementById('exactBoxes').value;

        const formData = new FormData();
        formData.append('ajax', 'true');
        formData.append('action', 'setExactProduction');
        formData.append('productId', currentExactProductId);
        formData.append('shift', currentExactShift);
        formData.append('exactBoxes', exactBoxes);

        fetch('index.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Actualizar los valores en la interfaz
                updateProductionUI(currentExactProductId, currentExactShift, data.data);

                // Actualizar el resumen de progreso por turno si existe
                if (data.summary) {
                    updateShiftProgressSummary(data.summary);
                }

                // Aplicar efecto de destello verde
                const productCard = document.getElementById(`product-${currentExactProductId}`);
                if (productCard) {
                    applyGreenFlash(productCard);
                }

                // Si completó la meta, mostrar notificación y aplicar efecto
                if (data.data.completed) {
                    applyCelebrationEffect(currentExactProductId);
                    showToast(`¡${data.data.productName} ha alcanzado su meta de producción!`, 'success');

                    // Actualizar estilo del turno a completado
                    updateShiftCompletedUI(currentExactProductId, currentExactShift);

                    // Actualizar contenido de la tarjeta para mostrar el mensaje de meta alcanzada
                    showCompletedUI(currentExactProductId);
                }
                // Si está cerca de la meta, mostrar notificación
                else if (data.data.closeToGoal) {
                    applyCloseToGoalEffect(currentExactProductId);
                    showToast(`¡${data.data.productName} está a 3 tarimas o menos de la meta!`, 'warning');
                }

                // Cerrar el modal
                closeExactProductionModal();

                // Mostrar mensaje en la interfaz
                showToast(data.message, 'info');
            }
        })
        .catch(error => console.error('Error:', error));
    }

    // Función para confirmar eliminación de producto
    function confirmDeleteProduct(productId, productName) {
        if (confirm(`¿Está seguro que desea eliminar el producto "${productName}"?`)) {
            document.getElementById('deleteProductId').value = productId;
            document.getElementById('deleteProductForm').submit();
        }
    }
    <?php endif; ?>

    // Función para verificar actualizaciones
    function checkForUpdates() {
        // Si la pestaña no está activa, postergar la verificación
        if (document.hidden) {
            console.log('Página no visible, se omite verificación');
            return;
        }
        
        // No verificar si se está editando un producto
        if (currentlyEditingProductId !== null) {
            console.log('Edición en curso, se omite verificación');
            return;
        }
        
        // Si está abierto algún modal, no verificar
        if (!document.getElementById('productModal')?.classList.contains('hidden') || 
            !document.getElementById('productionModal')?.classList.contains('hidden')) {
            console.log('Modal abierto, se omite verificación');
            return;
        }
        
        // Recolectar IDs de productos mostrados actualmente
        const productIds = [];
        document.querySelectorAll('#productList [id^="product-"]').forEach(card => {
            const id = card.id.replace('product-', '');
            productIds.push(id);
        });
        
// Obtener el turno actual
const currentShift = '<?php echo $shiftFilter; ?>';
        const currentMode = '<?php echo $mode; ?>';
        
        const formData = new FormData();
        formData.append('ajax', 'true');
        formData.append('action', 'checkUpdates');
        formData.append('lastUpdate', lastUpdateTimestamp);
        formData.append('shift', currentShift);
        formData.append('productIds', JSON.stringify(productIds));
        formData.append('mode', currentMode);
        
        // Agregamos una petición específica para obtener siempre el resumen actualizado
        formData.append('forceUpdateSummary', 'true');
        
        fetch('index.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Actualizar timestamp para próxima verificación
                if (data.serverTime) {
                    lastUpdateTimestamp = data.serverTime;
                }
                
                // Siempre actualizar el resumen si está disponible
                if (data.summary) {
                    updateShiftProgressSummary(data.summary);
                }
                
                // Si hay actualizaciones de productos individuales
                if (data.hasUpdates) {
                    console.log('Se detectaron actualizaciones en productos');
                    
                    // Actualizar productos individualmente
                    Object.values(data.updatedProducts).forEach(product => {
                        updateProductFromPolling(product, data.mode);
                    });
                    
                    // Mostrar notificación discreta
                    showToast('Datos de producción actualizados', 'info');
                }
            }
        })
        .catch(error => {
            console.error('Error al verificar actualizaciones:', error);
        });
    }

    // Función para actualizar un producto individual desde el polling
    function updateProductFromPolling(product, mode) {
        // No actualizar si es el producto que se está editando actualmente
        if (currentlyEditingProductId === product.id) {
            return;
        }
        
        // Actualizar total de cajas
        const totalBoxesElement = document.getElementById(`total-boxes-${product.id}-${product.shift}`);
        if (totalBoxesElement) {
            totalBoxesElement.textContent = `${product.totalBoxes} cajas`;
        }
        
        // Actualizar tarimas
        const palletsElement = document.getElementById(`pallets-${product.id}-${product.shift}`);
        if (palletsElement) {
            palletsElement.textContent = product.pallets;
        }
        
        // Actualizar cajas
        const boxesElement = document.getElementById(`boxes-${product.id}-${product.shift}`);
        if (boxesElement) {
            boxesElement.textContent = product.boxes;
        }
        
        // Actualizar hora de actualización
        const lastUpdateElement = document.getElementById(`last-update-${product.id}`);
        if (lastUpdateElement) {
            lastUpdateElement.textContent = product.lastUpdate;
        }
        
        // Actualizar barra de progreso si existe
        const productCard = document.getElementById(`product-${product.id}`);
        if (productCard) {
            // Buscar la barra de progreso dentro de este producto específico
            const progressBarElements = productCard.querySelectorAll('.flex.items-center.mb-2 .h-2.flex-grow .h-2');
            if (progressBarElements.length > 0) {
                progressBarElements[0].className = `h-2 ${product.progressColor} rounded-full`;
                progressBarElements[0].style.width = `${product.progress}%`;
                
                // Actualizar porcentaje
                const progressTextElements = productCard.querySelectorAll('.flex.items-center.mb-2 .ml-3.text-sm.font-bold');
                if (progressTextElements.length > 0) {
                    progressTextElements[0].textContent = `${product.progress}%`;
                }
            }
            
            // Actualizar datos restantes si no está completado
            if (!product.completed) {
                const remainingPalletsElement = document.getElementById(`remaining-pallets-${product.id}`);
                if (remainingPalletsElement) {
                    remainingPalletsElement.textContent = product.remainingPallets;
                }
                
                const remainingBoxesElement = document.getElementById(`remaining-boxes-${product.id}`);
                if (remainingBoxesElement) {
                    remainingBoxesElement.textContent = product.remainingBoxes;
                }
                
                const remainingTotalElement = document.getElementById(`remaining-total-${product.id}`);
                if (remainingTotalElement) {
                    remainingTotalElement.textContent = product.remainingTotal;
                }
            }
            
            // Aplicar efectos visuales basados en el estado
            applyGreenFlash(productCard);
            
            // Si completó la meta, mostrar notificación y aplicar efecto
            if (product.completed && !productCard.classList.contains('celebration-animation')) {
                applyCelebrationEffect(product.id);
                
                // Actualizar estilo del turno a completado
                updateShiftCompletedUI(product.id, product.shift);
                
                // Actualizar contenido de la tarjeta para mostrar el mensaje de meta alcanzada
                const infoSection = productCard.querySelector('.bg-white.p-4.rounded-lg.shadow-sm:not(.text-center)');
                if (infoSection && !infoSection.classList.contains('bg-green-100')) {
                    infoSection.className = 'bg-green-100 p-4 rounded-lg shadow-sm text-center';
                    infoSection.innerHTML = `
                    <div class="flex justify-center items-center">
                        <i class="fas fa-check-circle text-green-500 text-xl mr-2"></i>
                        <span class="font-bold text-green-700">¡META ALCANZADA!</span>
                    </div>
                    <div class="mt-2 text-sm text-green-600">Se han producido ${product.totalBoxes} cajas</div>
                    `;
                    
                    // Limpiar alertas en modo lectura
                    if (mode === 'read') {
                        const alertsSection = productCard.querySelector('.flex.flex-col.gap-2');
                        if (alertsSection) {
                            alertsSection.innerHTML = '';
                        }
                    }
                }
            }
            // Si está cerca de la meta, aplicar el efecto correspondiente
            else if (product.closeToGoal && !product.completed && !productCard.classList.contains('warning-animation')) {
                applyCloseToGoalEffect(product.id);
                
                // Asegurarse de que la alerta esté visible
                const alertsSection = productCard.querySelector('.flex.flex-col.gap-2');
                if (alertsSection) {
                    // Verificar si ya existe la alerta
                    const closeToGoalAlert = alertsSection.querySelector('.bg-yellow-100');
                    if (!closeToGoalAlert) {
                        // Agregar alerta de cerca de la meta
                        const alertHTML = `
                        <div class="flex items-center p-3 bg-yellow-100 text-yellow-800 rounded-lg text-sm">
                            <i class="fas fa-exclamation-circle mr-2 text-yellow-600"></i>
                            <span>Faltan 3 tarimas o menos para la meta</span>
                        </div>
                        `;
                        // Insertar al inicio
                        alertsSection.insertAdjacentHTML('afterbegin', alertHTML);
                    }
                }
            }
            
            // Actualizar estado de continuación
            const alertsSection = productCard.querySelector('.flex.flex-col.gap-2');
            if (alertsSection) {
                // Buscar alertas de continuación existentes
                const continuesAlerts = alertsSection.querySelectorAll('.bg-green-100:not(.rounded-lg.shadow-sm.text-center), .bg-red-100');
                
                // Eliminar alertas de continuación existentes
                continuesAlerts.forEach(alert => alert.remove());
                
                // Agregar nueva alerta según estado
                if (!product.completed) {
                    const continuesHTML = product.continues 
                        ? `
                        <div class="flex items-center p-3 bg-green-100 text-green-800 rounded-lg text-sm">
                            <i class="fas fa-check-circle mr-2 text-green-600"></i>
                            <span>Continúa en el siguiente turno</span>
                        </div>
                        `
                        : `
                        <div class="flex items-center p-3 bg-red-100 text-red-800 rounded-lg text-sm">
                            <i class="fas fa-exclamation-triangle mr-2 text-red-600"></i>
                            <span>NO continúa en el siguiente turno</span>
                        </div>
                        `;
                        
                    // Agregar después de cualquier alerta existente o al inicio
                    if (alertsSection.firstChild) {
                        alertsSection.firstChild.insertAdjacentHTML('afterend', continuesHTML);
                    } else {
                        alertsSection.insertAdjacentHTML('afterbegin', continuesHTML);
                    }
                }
            }
            
            // Actualizar el ícono de favorito si es necesario
            const favoriteButton = productCard.querySelector('.favorite-button');
            if (favoriteButton) {
                if (product.isFavorite && !favoriteButton.classList.contains('favorite-active')) {
                    favoriteButton.classList.add('favorite-active');
                    favoriteButton.classList.remove('text-gray-300', 'hover:text-gray-500');
                    const tooltip = favoriteButton.querySelector('.tooltip-text');
                    if (tooltip) tooltip.textContent = 'Quitar de favoritos';
                } else if (!product.isFavorite && favoriteButton.classList.contains('favorite-active')) {
                    favoriteButton.classList.remove('favorite-active');
                    favoriteButton.classList.add('text-gray-300', 'hover:text-gray-500');
                    const tooltip = favoriteButton.querySelector('.tooltip-text');
                    if (tooltip) tooltip.textContent = 'Añadir a favoritos';
                }
            }
        }
    }

    // Variables para el sistema de polling
    let lastUpdateTimestamp = Math.floor(Date.now() / 1000);
    let pollingInterval = 10000; // 10 segundos entre cada verificación
    let pollingTimer = null;
    let isPollingActive = true;
    let currentlyEditingProductId = null; // Para evitar actualizaciones durante la edición

    // Función para iniciar el polling
    function startPolling() {
        if (pollingTimer === null) {
            // Realizar la primera verificación después de un breve retraso
            setTimeout(checkForUpdates, 1000);
            
            // Establecer el intervalo para verificaciones periódicas
            pollingTimer = setInterval(checkForUpdates, pollingInterval);
            console.log('Sistema de actualización automática activado');
            
            // Actualizar indicador visual
            const indicator = document.getElementById('autoUpdateIndicator');
            if (indicator) {
                indicator.className = 'auto-update-indicator auto-update-active';
            }
            
            // Actualizar indicador del botón
            const buttonIndicator = document.getElementById('pollingIndicator');
            if (buttonIndicator) {
                buttonIndicator.className = 'w-3 h-3 bg-green-500 rounded-full absolute bottom-0 right-0';
            }
        }
    }

    // Función para detener el polling
    function stopPolling() {
        if (pollingTimer !== null) {
            clearInterval(pollingTimer);
            pollingTimer = null;
            console.log('Sistema de actualización automática desactivado');
            
            // Actualizar indicador visual
            const indicator = document.getElementById('autoUpdateIndicator');
            if (indicator) {
                indicator.className = 'auto-update-indicator auto-update-inactive';
            }
            
            // Actualizar indicador del botón
            const buttonIndicator = document.getElementById('pollingIndicator');
            if (buttonIndicator) {
                buttonIndicator.className = 'w-3 h-3 bg-gray-500 rounded-full absolute bottom-0 right-0';
            }
        }
    }
    
    // Evento de visibilidad para optimizar el polling
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            // La página no está visible, podríamos pausar o reducir el polling
            console.log('Página oculta, polling en modo de espera');
        } else {
            // La página está visible de nuevo, hacer una verificación inmediata
            console.log('Página visible, verificando actualizaciones');
            checkForUpdates();
        }
    });

    // Modificar funciones existentes para coordinar con el sistema de polling
    <?php if ($mode === EDIT_MODE): ?>
    // Interceptar la función de edición para marcar el producto como en edición
    const originalEditProduct = window.editProduct;
    window.editProduct = function(product) {
        currentlyEditingProductId = product.id;
        originalEditProduct(product);
    };

    // Interceptar el cierre del modal para desmarcar el producto en edición
    const originalCloseProductModal = window.closeProductModal;
    window.closeProductModal = function() {
        currentlyEditingProductId = null;
        originalCloseProductModal();
    };
    <?php endif; ?>

    // Hacer clickeables los bloques de progreso por turno
    document.getElementById('morning-progress-section')?.addEventListener('click', function() {
        window.location.href = '<?php echo $morningShiftUrl; ?>';
    });

    document.getElementById('afternoon-progress-section')?.addEventListener('click', function() {
        window.location.href = '<?php echo $afternoonShiftUrl; ?>';
    });

    document.getElementById('night-progress-section')?.addEventListener('click', function() {
        window.location.href = '<?php echo $nightShiftUrl; ?>';
    });
    
    // Añadir eventos para bloques de "Todos los turnos" si existen
    document.getElementById('morning-shift-block')?.addEventListener('click', function() {
        window.location.href = '<?php echo $morningShiftUrl; ?>';
    });
    
    document.getElementById('afternoon-shift-block')?.addEventListener('click', function() {
        window.location.href = '<?php echo $afternoonShiftUrl; ?>';
    });
    
    document.getElementById('night-shift-block')?.addEventListener('click', function() {
        window.location.href = '<?php echo $nightShiftUrl; ?>';
    });
    
    // Añadir evento al botón de toggle de polling
    document.getElementById('togglePollingButton')?.addEventListener('click', function() {
        togglePolling();
    });
    
    // Iniciar el sistema de polling cuando se carga la página
    document.addEventListener('DOMContentLoaded', function() {
        startPolling();
    });
    </script>






<script>
// Agregamos un botón simple para WhatsApp
document.addEventListener('DOMContentLoaded', function() {
    // Crear el botón
    const whatsappButton = document.createElement('div');
    whatsappButton.className = 'relative tooltip';
    whatsappButton.innerHTML = `
        <button id="whatsappShareButton" class="p-2 bg-green-500 hover:bg-green-600 text-white rounded-full relative shadow-sm">
            <i class="fab fa-whatsapp"></i>
            <span class="tooltip-text">Compartir por WhatsApp</span>
        </button>
    `;
    
    // Agregar el botón después del botón de notificaciones
    const notificationButtonContainer = document.querySelector('#notificationButton').parentNode;
    notificationButtonContainer.parentNode.insertBefore(whatsappButton, notificationButtonContainer.nextSibling);
    
    // Agregar event listener - Usar método directo
    document.getElementById('whatsappShareButton').addEventListener('click', function() {
        shareToWhatsApp();
    });
});

// Función para compartir directamente a WhatsApp
function shareToWhatsApp() {
    // Capturar TODA la página como texto
    const fullPageText = document.body.innerText;
    
    // Fecha y hora actual
    const now = new Date();
    const fecha = now.toLocaleDateString('es-MX');
    const hora = now.toLocaleTimeString('es-MX');
    
    // 1. Preparar el encabezado del mensaje
    let mensaje = `🌟 *REPORTE DE PRODUCCIÓN* 🌟\n`;
    mensaje += `━━━━━━━━━━━━━━━━━━━━━━━\n`;
    mensaje += `📅 *Fecha:* ${fecha}\n`;
    mensaje += `⏰ *Hora:* ${hora}\n`;
    mensaje += `━━━━━━━━━━━━━━━━━━━━━━━\n\n`;
    
    // 2. Detectar el turno actual - Basado en URL y texto visible
    let currentShift = "Todos";
    let currentShiftKey = "all";
    
    if (document.URL.includes("shift=morning") || 
        fullPageText.includes("TURNO MAÑANA") || 
        document.querySelector('.shift-selector.active')?.textContent.includes('Mañana')) {
        currentShift = "Mañana";
        currentShiftKey = "morning";
        mensaje += `☀️☀️☀️\n*TURNO MAÑANA*\n☀️☀️☀️\n\n`;
    } else if (document.URL.includes("shift=afternoon") || 
               fullPageText.includes("TURNO TARDE") || 
               document.querySelector('.shift-selector.active')?.textContent.includes('Tarde')) {
        currentShift = "Tarde";
        currentShiftKey = "afternoon";
        mensaje += `🌤️🌤️🌤️\n*TURNO TARDE*\n🌤️🌤️🌤️\n\n`;
    } else if (document.URL.includes("shift=night") || 
               fullPageText.includes("TURNO NOCHE") || 
               document.querySelector('.shift-selector.active')?.textContent.includes('Noche')) {
        currentShift = "Noche";
        currentShiftKey = "night";
        mensaje += `🌙🌙🌙\n*TURNO NOCHE*\n🌙🌙🌙\n\n`;
    } else {
        mensaje += `☀️🌤️🌙\n*TODOS LOS TURNOS*\n☀️🌤️🌙\n\n`;
    }
    
    // Buscar información sobre el turno actual
    mensaje += `✨ *DETALLES DEL TURNO* ✨\n\n`;
    
    if (currentShift !== "Todos") {
        // Si estamos en un turno específico, capturar información detallada solo de ese turno
        const turnoData = capturarDatosTurnoEspecifico(currentShift, currentShiftKey, fullPageText);
        
        mensaje += `${turnoData.emoji} *${currentShift}*\n`;
        
        // Agregar progreso
        if (turnoData.progreso) {
            mensaje += `▸ Progreso: ${turnoData.progreso}\n`;
        }
        
        // Agregar tarimas
        if (turnoData.tarimas) {
            mensaje += `▸ Tarimas: ${turnoData.tarimas}\n`;
        }
        
        // Agregar faltantes
        if (turnoData.faltantes) {
            mensaje += `▸ Faltantes: ${turnoData.faltantes}\n`;
        }
        
        // Agregar cajas
        if (turnoData.cajas) {
            mensaje += `▸ Cajas: ${turnoData.cajas}\n`;
        }
        
        // Agregar información adicional
        if (turnoData.infoAdicional && turnoData.infoAdicional.length > 0) {
            turnoData.infoAdicional.forEach(info => {
                mensaje += `▸ ${info}\n`;
            });
        }
    } else {
        // Si es todos los turnos, mostrar resumen de cada uno
        const turnos = [
            { nombre: "Mañana", emoji: "☀️", key: "morning" },
            { nombre: "Tarde", emoji: "🌤️", key: "afternoon" },
            { nombre: "Noche", emoji: "🌙", key: "night" }
        ];
        
        turnos.forEach(turno => {
            const datos = capturarDatosTurnoEspecifico(turno.nombre, turno.key, fullPageText);
            
            // Solo mostrar el turno si tiene algún dato
            if (datos.progreso || datos.tarimas || datos.faltantes) {
                mensaje += `${turno.emoji} *${turno.nombre}:*\n`;
                
                if (datos.progreso) {
                    mensaje += `▸ Progreso: ${datos.progreso}\n`;
                }
                
                if (datos.tarimas) {
                    mensaje += `▸ Tarimas: ${datos.tarimas}\n`;
                }
                
                if (datos.faltantes) {
                    mensaje += `▸ Faltantes: ${datos.faltantes}\n`;
                }
                
                mensaje += `\n`;
            }
        });
    }
    
    mensaje += `━━━━━━━━━━━━━━━━━━━━━━━\n\n`;
    
    // 3. Capturar datos de estadísticas de productos
    mensaje += `🔍 *ESTADO DE PRODUCTOS* 🔍\n\n`;
    
    // Buscar patrones para productos completados, cerca de meta y pendientes
    const completadosMatch = /completados\s*:?\s*(\d+)/i.exec(fullPageText) || 
                            /productos\s*completados\s*:?\s*(\d+)/i.exec(fullPageText);
    
    const cercaMetaMatch = /cerca(?:nos)?\s*(?:de|a)?\s*meta\s*:?\s*(\d+)/i.exec(fullPageText) || 
                          /cercanos\s*:?\s*(\d+)/i.exec(fullPageText);
    
    const pendientesMatch = /pendientes\s*:?\s*(\d+)/i.exec(fullPageText);
    
    if (completadosMatch) {
        mensaje += `✅ Completados: ${completadosMatch[1]}\n`;
    }
    
    if (cercaMetaMatch) {
        mensaje += `⚠️ Cerca de meta: ${cercaMetaMatch[1]}\n`;
    }
    
    if (pendientesMatch) {
        mensaje += `⏳ Pendientes: ${pendientesMatch[1]}\n`;
    }
    
    mensaje += `\n━━━━━━━━━━━━━━━━━━━━━━━\n\n`;
    
    // 4. Capturar productos visibles del turno actual
    mensaje += `📋 *PRODUCTOS DEL TURNO ${currentShift.toUpperCase()}* 📋\n\n`;
    
    // Obtener los productos visibles actualmente
    const productos = capturarProductosVisibles(currentShift);
    
    if (productos.length === 0) {
        mensaje += `❌ No hay productos visibles en el turno ${currentShift}.\n`;
    } else {
        // Mostrar cada producto con todos sus datos relevantes
        productos.forEach(prod => {
            mensaje += `${prod.status} *${prod.index}. ${prod.name}*\n`;
            
            if (prod.turnos.length > 0 && currentShift === "Todos") {
                mensaje += `   └ Turnos: ${prod.turnos.join(', ')}\n\n`;
            } else {
                mensaje += `\n`;
            }
            
            // Datos generales del producto
            mensaje += `   📊 *Datos de producción:*\n`;
            
            if (prod.meta !== '?') {
                mensaje += `   ├ Meta: ${prod.meta} cajas\n`;
            }
            
            if (prod.produccion !== '?') {
                mensaje += `   ├ Producción: ${prod.produccion} ${prod.meta !== '?' ? 'de ' + prod.meta : ''} cajas`;
                
                if (prod.progreso !== '?%') {
                    mensaje += ` (${prod.progreso})`;
                }
                
                mensaje += `\n`;
            } else if (prod.progreso !== '?%') {
                mensaje += `   ├ Progreso: ${prod.progreso}\n`;
            }
            
            if (prod.tarimas !== '?' || prod.cajas !== '?') {
                mensaje += `   ├ Tarimas: ${prod.tarimas} | Cajas: ${prod.cajas}\n`;
            }
            
            if (prod.status !== '✅' && prod.faltantes !== '?') {
                mensaje += `   ├ Faltante: ${prod.faltantes} cajas\n`;
            }
            
            // Si tenemos información de último cambio, mostrarla
            if (prod.ultimaActualizacion) {
                mensaje += `   ├ Última actualización: ${prod.ultimaActualizacion}\n`;
            }
            
            // Si hay información específica del turno, mostrarla
            if (prod.infoEspecifica && prod.infoEspecifica.length > 0) {
                mensaje += `\n   📌 *Detalles específicos:*\n`;
                
                prod.infoEspecifica.forEach((info, idx) => {
                    const prefijo = idx < prod.infoEspecifica.length - 1 ? '   ├ ' : '   └ ';
                    mensaje += `${prefijo}${info}\n`;
                });
            }
            
            // Estado final
            if (prod.status === '✅') {
                mensaje += `   └ ✅ Completado\n`;
            } else if (prod.status === '⚠️') {
                mensaje += `   └ ⚠️ Cerca de meta\n`;
            } else {
                mensaje += `   └ ⏳ En proceso\n`;
            }
            
            mensaje += `\n·················································\n\n`;
        });
    }
    
    // Añadir pie de página
    mensaje += `━━━━━━━━━━━━━━━━━━━━━━━\n`;
    mensaje += `🏭 *Monitoreo de Producción* 🏭\n`;
    mensaje += `Reporte generado: ${fecha} ${hora}\n`;
    
    // Abrir WhatsApp con el mensaje
    const encodedMessage = encodeURIComponent(mensaje);
    window.open(`https://api.whatsapp.com/send?text=${encodedMessage}`, '_blank');
}

// Función auxiliar para capturar datos específicos de un turno
function capturarDatosTurnoEspecifico(nombreTurno, keyTurno, fullPageText) {
    const resultado = {
        emoji: nombreTurno === "Mañana" ? "☀️" : (nombreTurno === "Tarde" ? "🌤️" : "🌙"),
        progreso: null,
        tarimas: null,
        faltantes: null,
        cajas: null,
        infoAdicional: []
    };
    
    // MÉTODO 1: Buscar el texto relacionado con este turno en toda la página
    // Capturar un contexto amplio alrededor del nombre del turno
    const turnoIndex = fullPageText.indexOf(nombreTurno);
    
    if (turnoIndex !== -1) {
        // Tomar 500 caracteres antes y después del nombre del turno
        const inicio = Math.max(0, turnoIndex - 500);
        const fin = Math.min(fullPageText.length, turnoIndex + 500);
        const contexto = fullPageText.substring(inicio, fin);
        
        // Extraer progreso con cualquier patrón posible
        const progresoPatterns = [
            new RegExp(`${nombreTurno}[\\s\\S]*?(\\d+)%`, 'i'),
            new RegExp(`Progreso[\\s\\S]*?${nombreTurno}[\\s\\S]*?(\\d+)%`, 'i'),
            new RegExp(`${nombreTurno}[\\s\\S]*?Progreso[\\s\\S]*?(\\d+)%`, 'i'),
            /(\d+)%/
        ];
        
        for (const pattern of progresoPatterns) {
            const match = pattern.exec(contexto);
            if (match) {
                resultado.progreso = `${match[1]}%`;
                break;
            }
        }
        
        // Extraer tarimas con cualquier patrón posible
        const tarimasPatterns = [
            new RegExp(`${nombreTurno}[\\s\\S]*?(\\d+)\\s*(?:de|\\/)\\s*(\\d+)\\s*tarimas`, 'i'),
            new RegExp(`tarimas[\\s\\S]*?${nombreTurno}[\\s\\S]*?(\\d+)\\s*(?:de|\\/)\\s*(\\d+)`, 'i'),
            new RegExp(`${nombreTurno}[\\s\\S]*?tarimas[\\s\\S]*?(\\d+)\\s*(?:de|\\/)\\s*(\\d+)`, 'i'),
            /(\d+)\s*(?:de|\/)\s*(\d+)\s*tarimas/i,
            /tarimas\s*:?\s*(\d+)\s*(?:de|\/)\s*(\d+)/i
        ];
        
        for (const pattern of tarimasPatterns) {
            const match = pattern.exec(contexto);
            if (match) {
                resultado.tarimas = `${match[1]} de ${match[2]}`;
                
                // Calcular faltantes
                const producidas = parseInt(match[1]);
                const totales = parseInt(match[2]);
                const faltantes = totales - producidas;
                
                if (faltantes > 0) {
                    resultado.faltantes = `${faltantes} tarimas`;
                }
                
                break;
            }
        }
        
        // Extraer cajas con cualquier patrón posible
        const cajasPatterns = [
            new RegExp(`${nombreTurno}[\\s\\S]*?(\\d+)\\s*cajas`, 'i'),
            new RegExp(`cajas[\\s\\S]*?${nombreTurno}[\\s\\S]*?(\\d+)`, 'i'),
            new RegExp(`${nombreTurno}[\\s\\S]*?cajas[\\s\\S]*?(\\d+)`, 'i'),
            /cajas\s*:?\s*(\d+)/i
        ];
        
        for (const pattern of cajasPatterns) {
            const match = pattern.exec(contexto);
            if (match) {
                resultado.cajas = `${match[1]} cajas`;
                break;
            }
        }
        
        // Buscar información adicional específica de este turno
        const lineasContexto = contexto.split('\n');
        const lineasRelevantes = lineasContexto.filter(linea => 
            linea.includes(nombreTurno) || 
            (linea.includes("tarima") && linea.includes("caja"))
        );
        
        if (lineasRelevantes.length > 0) {
            lineasRelevantes.forEach(linea => {
                const lineaLimpia = linea.trim();
                // Solo agregar si es una línea significativa
                if (lineaLimpia.length > 5 && 
                    !lineaLimpia.includes("Progreso") && 
                    !resultado.infoAdicional.includes(lineaLimpia)) {
                    resultado.infoAdicional.push(lineaLimpia);
                }
            });
        }
    }
    
    // MÉTODO 2: Buscar elementos específicos en el DOM
    // Usando selectores específicos para el turno
    const selectores = [
        `#${keyTurno}-progress-section`, 
        `#${keyTurno}-shift-block`,
        `[id*="${keyTurno}"]`,
        `[class*="${keyTurno}"]`,
        `[id*="${nombreTurno.toLowerCase()}"]`,
        `[class*="${nombreTurno.toLowerCase()}"]`
    ];
    
    for (const selector of selectores) {
        const elementos = document.querySelectorAll(selector);
        elementos.forEach(elemento => {
            const textoElemento = elemento.innerText || elemento.textContent;
            
            // Si el elemento contiene información relevante
            if (textoElemento && 
                (textoElemento.includes("%") || 
                 textoElemento.includes("tarima") || 
                 textoElemento.includes("caja"))) {
                
                // Buscar progreso si no lo tenemos
                if (!resultado.progreso) {
                    const progresoMatch = textoElemento.match(/(\d+)%/);
                    if (progresoMatch) {
                        resultado.progreso = progresoMatch[0];
                    }
                }
                
                // Buscar tarimas si no las tenemos
                if (!resultado.tarimas) {
                    const tarimasMatch = textoElemento.match(/(\d+)\s*(?:de|\/)\s*(\d+)\s*tarimas/i);
                    if (tarimasMatch) {
                        resultado.tarimas = `${tarimasMatch[1]} de ${tarimasMatch[2]}`;
                        
                        // Calcular faltantes
                        const producidas = parseInt(tarimasMatch[1]);
                        const totales = parseInt(tarimasMatch[2]);
                        const faltantes = totales - producidas;
                        
                        if (faltantes > 0) {
                            resultado.faltantes = `${faltantes} tarimas`;
                        }
                    }
                }
            }
        });
    }
    
    return resultado;
}

// Función auxiliar para capturar productos visibles, con enfoque especial en el turno indicado
function capturarProductosVisibles(turnoActual) {
    const productos = [];
    
    document.querySelectorAll('#productList [id^="product-"]').forEach((card, index) => {
        try {
            // Capturar todo el texto de la tarjeta
            const cardText = card.innerText || card.textContent;
            if (!cardText) return;
            
            // Dividir en líneas y filtrar vacías
            const lines = cardText.split('\n').map(l => l.trim()).filter(l => l);
            
            // Encontrar el nombre del producto
            let productName = "Producto desconocido";
            for (let i = 0; i < Math.min(5, lines.length); i++) {
                if (lines[i] && lines[i].length > 3 && 
                    !lines[i].includes('%') && 
                    !lines[i].includes('tarima') && 
                    !lines[i].includes('caja')) {
                    productName = lines[i];
                    break;
                }
            }
            
            // Determinar estado
            let status = "⏳";
            if (cardText.includes('COMPLETADO') || cardText.includes('META ALCANZADA')) {
                status = "✅";
            } else if (cardText.includes('CERCA DE META')) {
                status = "⚠️";
            }
            
            // Buscar turnos configurados
            const turnos = [];
            if (cardText.includes('Mañana')) turnos.push('Mañana');
            if (cardText.includes('Tarde')) turnos.push('Tarde');
            if (cardText.includes('Noche')) turnos.push('Noche');
            
            // Si estamos en un turno específico, verificar si este producto pertenece al turno
            if (turnoActual !== "Todos" && !turnos.includes(turnoActual)) {
                // Si no pertenece al turno actual, no lo incluimos
                return;
            }
            
            // Extraer datos generales
            const metaMatch = cardText.match(/Meta\s*:?\s*(\d+)/i);
            const produccionMatch = cardText.match(/Producción\s*:?\s*(\d+)/i) || 
                                   cardText.match(/(\d+)\s*de\s*(\d+)\s*cajas/i);
            const progresoMatch = cardText.match(/(\d+)%/);
            const tarimasMatch = cardText.match(/Tarimas\s*:?\s*(\d+)/i);
            const cajasMatch = cardText.match(/Cajas\s*:?\s*(\d+)/i);
            const faltantesMatch = cardText.match(/Faltante\s*:?\s*(\d+)/i);
            const ultimaActualizacionMatch = cardText.match(/(\d{1,2}\/\d{1,2}\/\d{2,4}\s+\d{1,2}:\d{2})/);
            
            // Extraer información específica del turno actual
            const infoEspecifica = [];
            
            // Si estamos en un turno específico, buscar información detallada
            if (turnoActual !== "Todos") {
                let capturaEspecifica = false;
                
                // Buscar secciones específicas para el turno actual
                for (let i = 0; i < lines.length; i++) {
                    if (lines[i].includes(turnoActual)) {
                        capturaEspecifica = true;
                        
                        // Capturar las siguientes líneas que contengan información relevante
                        for (let j = i + 1; j < lines.length && j < i + 10; j++) {
                            if (lines[j].includes(turnoActual === "Mañana" ? "Tarde" : 
                                                (turnoActual === "Tarde" ? "Noche" : "Mañana"))) {
                                // Llegamos a otro turno, detenemos la captura
                                break;
                            }
                            
                            // Capturar líneas que contengan información específica
                            if (lines[j].includes("Meta") || 
                                lines[j].includes("cajas") || 
                                lines[j].includes("tarima") || 
                                lines[j].includes("Producción") || 
                                lines[j].includes("Faltante") || 
                                lines[j].includes("%")) {
                                
                                // Evitar duplicados
                                if (!infoEspecifica.includes(lines[j])) {
                                    infoEspecifica.push(lines[j]);
                                }
                            }
                        }
                    }
                }
            }
            
            // Añadir a la lista de productos
            productos.push({
                index: index + 1,
                name: productName,
                status: status,
                turnos: turnos,
                meta: metaMatch ? metaMatch[1] : '?',
                produccion: produccionMatch ? produccionMatch[1] : '?',
                progreso: progresoMatch ? progresoMatch[1] + '%' : '?%',
                tarimas: tarimasMatch ? tarimasMatch[1] : '?',
                cajas: cajasMatch ? cajasMatch[1] : '?',
                faltantes: faltantesMatch ? faltantesMatch[1] : '?',
                ultimaActualizacion: ultimaActualizacionMatch ? ultimaActualizacionMatch[1] : null,
                infoEspecifica: infoEspecifica
            });
        } catch (e) {
            console.error("Error al procesar producto:", e);
        }
    });
    
    return productos;
}
</script>










    
    </body>
    </html>