<?php
// src/recommendations.php - Smart Product Recommendation Engine

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Log product interaction (enhanced version)
 */
function log_product_interaction($data) {
    global $db;
    
    $userId = $data['user_id'] ?? null;
    $sessionId = $data['session_id'] ?? null;
    $productId = $data['product_id'];
    $interactionType = $data['interaction_type'];
    $rating = $data['rating'] ?? null;
    $priceAtTime = $data['price_at_time'] ?? null;
    $referrer = $data['referrer'] ?? null;
    $ipAddress = $data['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? null);
    
    $stmt = $db->prepare("
        INSERT INTO product_interactions 
        (user_id, session_id, product_id, interaction_type, rating, price_at_time, referrer, ip_address)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([$userId, $sessionId, $productId, $interactionType, $rating, $priceAtTime, $referrer, $ipAddress]);
    
    // Also log to product_views if it's a view
    if ($interactionType === 'view') {
        log_product_view($userId, $sessionId, $productId, $referrer);
        
        // Update user preferences
        if ($userId) {
            update_user_view_preferences($userId, $productId);
        }
        
        // Update session-based frequently viewed
        update_frequently_viewed_together($sessionId, $productId);
    }
    
    // Update recommendations after new interaction
    if ($userId && in_array($interactionType, ['purchase', 'wishlist'])) {
        generate_personalized_recommendations($userId);
    }
    
    return $db->lastInsertId();
}

/**
 * Log detailed product view
 */
function log_product_view($userId, $sessionId, $productId, $referrer = null) {
    global $db;
    
    // Detect device type
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $deviceType = 'desktop';
    if (preg_match('/mobile/i', $userAgent)) {
        $deviceType = 'mobile';
    } elseif (preg_match('/tablet|iPad/i', $userAgent)) {
        $deviceType = 'tablet';
    }
    
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    
    $stmt = $db->prepare("
        INSERT INTO product_views 
        (user_id, session_id, product_id, referrer, device_type, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([$userId, $sessionId, $productId, $referrer, $deviceType, $ipAddress]);
    
    // Also log to browsing_history
    log_browsing_history($userId, $sessionId, $productId);
    
    return $db->lastInsertId();
}

/**
 * Log browsing history entry
 */
function log_browsing_history($userId, $sessionId, $productId, $action = 'view', $searchQuery = null) {
    global $db;
    
    // Get product category
    $categoryId = null;
    $stmt = $db->prepare("SELECT category_id FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if ($product) {
        $categoryId = $product['category_id'];
    }
    
    // Get sequence order for this session
    $seqStmt = $db->prepare("
        SELECT COALESCE(MAX(sequence_order), 0) + 1 as next_order 
        FROM browsing_history 
        WHERE session_id = ? OR user_id = ?
    ");
    $seqStmt->execute([$sessionId, $userId]);
    $seqResult = $seqStmt->fetch();
    $sequenceOrder = $seqResult['next_order'] ?? 1;
    
    $insertStmt = $db->prepare("
        INSERT INTO browsing_history 
        (user_id, session_id, product_id, category_id, search_query, action, sequence_order)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $insertStmt->execute([$userId, $sessionId, $productId, $categoryId, $searchQuery, $action, $sequenceOrder]);
    
    return $db->lastInsertId();
}

/**
 * Update frequently viewed together pairs
 */
function update_frequently_viewed_together($sessionId, $newProductId) {
    global $db;
    
    if (empty($sessionId)) return;
    
    // Get products viewed in this session in the last 30 minutes
    $stmt = $db->prepare("
        SELECT DISTINCT product_id, viewed_at 
        FROM product_views 
        WHERE session_id = ? 
        AND viewed_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        AND product_id != ?
        ORDER BY viewed_at DESC
        LIMIT 10
    ");
    $stmt->execute([$sessionId, $newProductId]);
    $recentViews = $stmt->fetchAll();
    
    foreach ($recentViews as $view) {
        $existingProductId = $view['product_id'];
        
        // Check if pair exists
        $checkStmt = $db->prepare("
            SELECT id, view_count, session_count 
            FROM frequently_viewed_together 
            WHERE (product_a_id = ? AND product_b_id = ?)
            OR (product_a_id = ? AND product_b_id = ?)
        ");
        $checkStmt->execute([$newProductId, $existingProductId, $existingProductId, $newProductId]);
        $existing = $checkStmt->fetch();
        
        if ($existing) {
            // Update existing pair - increment both counts
            $updateStmt = $db->prepare("
                UPDATE frequently_viewed_together 
                SET view_count = view_count + 1, 
                    session_count = session_count + 1,
                    last_seen = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$existing['id']]);
        } else {
            // Insert new pair
            $insertStmt = $db->prepare("
                INSERT INTO frequently_viewed_together 
                (product_a_id, product_b_id, view_count, session_count)
                VALUES (?, ?, 1, 1)
            ");
            $insertStmt->execute([$newProductId, $existingProductId]);
        }
    }
}

/**
 * Update user view preferences
 */
function update_user_view_preferences($userId, $productId) {
    global $db;
    
    // Get product category
    $stmt = $db->prepare("SELECT category_id, price FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) return;
    
    // Check if user preferences exist
    $prefStmt = $db->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
    $prefStmt->execute([$userId]);
    $preferences = $prefStmt->fetch();
    
    $categoryId = $product['category_id'];
    $price = $product['price'];
    
    if ($preferences) {
        // Update existing preferences
        $viewedCategories = json_decode($preferences['viewed_categories'] ?? '[]', true) ?: [];
        $browsingHistory = json_decode($preferences['browsing_history'] ?? '[]', true) ?: [];
        
        // Add category if not exists
        if (!in_array($categoryId, $viewedCategories)) {
            $viewedCategories[] = $categoryId;
        }
        
        // Add to browsing history (keep last 50)
        $browsingHistory[] = [
            'product_id' => $productId,
            'category_id' => $categoryId,
            'viewed_at' => date('Y-m-d H:i:s')
        ];
        if (count($browsingHistory) > 50) {
            $browsingHistory = array_slice($browsingHistory, -50);
        }
        
        // Update price range
        $priceRange = json_decode($preferences['preferred_price_range'] ?? '{"min":null,"max":null}', true);
        if (!isset($priceRange['min']) || $price < $priceRange['min']) {
            $priceRange['min'] = $price;
        }
        if (!isset($priceRange['max']) || $price > $priceRange['max']) {
            $priceRange['max'] = $price;
        }
        
        $updateStmt = $db->prepare("
            UPDATE user_preferences 
            SET viewed_categories = ?,
                browsing_history = ?,
                preferred_price_range = ?,
                last_updated = NOW()
            WHERE user_id = ?
        ");
        
        $updateStmt->execute([
            json_encode($viewedCategories),
            json_encode($browsingHistory),
            json_encode($priceRange),
            $userId
        ]);
    } else {
        // Create new preferences
        $insertStmt = $db->prepare("
            INSERT INTO user_preferences 
            (user_id, viewed_categories, preferred_price_range, browsing_history)
            VALUES (?, ?, ?, ?)
        ");
        
        $priceRange = ['min' => $price, 'max' => $price];
        $browsingHistory = [
            ['product_id' => $productId, 'category_id' => $categoryId, 'viewed_at' => date('Y-m-d H:i:s')]
        ];
        
        $insertStmt->execute([
            $userId,
            json_encode([$categoryId]),
            json_encode($priceRange),
            json_encode($browsingHistory)
        ]);
    }
}

/**
 * Get frequently viewed together products
 */
function get_frequently_viewed_together($productId, $limit = 6) {
    global $db;
    
    $stmt = $db->prepare("
        SELECT 
            CASE 
                WHEN product_a_id = ? THEN product_b_id 
                ELSE product_a_id 
            END as product_id,
            fvt.view_count,
            fvt.session_count,
            p.name, p.price, p.image, p.category_id,
            (fvt.view_count / fvt.session_count) as view_score
        FROM frequently_viewed_together fvt
        JOIN products p ON p.id = CASE 
            WHEN fvt.product_a_id = ? THEN fvt.product_b_id 
            ELSE fvt.product_a_id 
        END
        WHERE (fvt.product_a_id = ? OR fvt.product_b_id = ?)
        AND p.is_active = 1 AND p.visible = 1
        ORDER BY view_score DESC
        LIMIT ?
    ");
    
    $stmt->execute([$productId, $productId, $productId, $productId, $limit]);
    $products = $stmt->fetchAll();
    
    if (empty($products)) {
        // Fallback to similar products
        return get_similar_products($productId, $limit);
    }
    
    return $products;
}

/**
 * Get browsing-based recommendations
 */
function get_browsing_based_recommendations($userId, $limit = 10) {
    global $db;
    
    // Get user preferences
    $stmt = $db->prepare("
        SELECT viewed_categories, preferred_price_range, browsing_history 
        FROM user_preferences 
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $preferences = $stmt->fetch();
    
    if (!$preferences || empty($preferences['viewed_categories'])) {
        return get_popular_products(null, $limit);
    }
    
    $viewedCategories = json_decode($preferences['viewed_categories'], true);
    $priceRange = json_decode($preferences['preferred_price_range'] ?? '{"min":0,"max":999999}', true);
    $browsingHistory = json_decode($preferences['browsing_history'] ?? '[]', true);
    
    // Get recently viewed product IDs to exclude
    $recentlyViewed = array_slice($browsingHistory, -20);
    $recentProductIds = array_column($recentlyViewed, 'product_id');
    
    if (empty($viewedCategories)) {
        return get_popular_products(null, $limit);
    }
    
    $catPlaceholders = implode(',', array_fill(0, count($viewedCategories), '?'));
    
    $sql = "
        SELECT p.id, p.name, p.price, p.image, p.category_id,
            COUNT(DISTINCT pv.id) as view_count,
            AVG(CASE WHEN pi.rating IS NOT NULL THEN pi.rating ELSE 0 END) as avg_rating,
            (
                SELECT COUNT(*) FROM browsing_history bh 
                WHERE bh.product_id = p.id AND bh.user_id = ?
            ) as user_views
        FROM products p
        LEFT JOIN product_views pv ON p.id = pv.product_id
        LEFT JOIN product_interactions pi ON p.id = pi.product_id
        WHERE p.category_id IN ($catPlaceholders)
        AND p.is_active = 1 AND p.visible = 1
    ";
    
    $params = array_merge([$userId], $viewedCategories);
    
    if (!empty($recentProductIds)) {
        $prodPlaceholders = implode(',', array_fill(0, count($recentProductIds), '?'));
        $sql .= " AND p.id NOT IN ($prodPlaceholders)";
        $params = array_merge($params, $recentProductIds);
    }
    
    // Add price range filter if available
    if (isset($priceRange['min']) && isset($priceRange['max'])) {
        $sql .= " AND p.price BETWEEN ? AND ?";
        $params[] = $priceRange['min'];
        $params[] = $priceRange['max'];
    }
    
    $sql .= "
        GROUP BY p.id
        ORDER BY user_views DESC, view_count DESC, avg_rating DESC
        LIMIT ?
    ";
    $params[] = $limit;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    if (empty($products)) {
        return get_category_recommendations($viewedCategories[0], $limit);
    }
    
    return $products;
}

/**
 * Get trending products (based on recent views)
 * Falls back to popular products if no recent views exist
 */
function get_trending_products($limit = 10) {
    global $db;
    
    $stmt = $db->prepare("
        SELECT p.id, p.name, p.price, p.image, p.category_id,
            COUNT(pv.id) as recent_views,
            SUM(CASE WHEN pv.viewed_at > DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) as today_views,
            SUM(CASE WHEN pv.viewed_at > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as week_views,
            COALESCE(AVG(pi.rating), 0) as avg_rating
        FROM products p
        LEFT JOIN product_views pv ON p.id = pv.product_id
        LEFT JOIN product_interactions pi ON p.id = pi.product_id AND pi.interaction_type = 'purchase'
        WHERE p.is_active = 1 AND p.visible = 1
        AND pv.viewed_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY p.id
        ORDER BY week_views DESC, today_views DESC, recent_views DESC
        LIMIT ?
    ");
    
    $stmt->execute([$limit]);
    $products = $stmt->fetchAll();
    
    // If no trending data (no recent views), return popular products as fallback
    if (empty($products)) {
        return get_popular_products(null, $limit);
    }
    
    // Calculate trend score
    $maxWeekViews = 1;
    $weekViews = array_column($products, 'week_views');
    if (!empty($weekViews)) {
        $maxWeekViews = max($weekViews);
    }
    
    foreach ($products as &$product) {
        $product['score'] = ($product['week_views'] / $maxWeekViews) * 0.7 + 
                          ($product['today_views'] / max($product['week_views'], 1)) * 0.3;
    }
    
    usort($products, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    return $products;
}

/**
 * Get personalized recommendations for user
 */
function get_personalized_recommendations($userId, $limit = 10) {
    global $db;
    
    // Check cache first
    $stmt = $db->prepare("
        SELECT pr.*, p.name, p.price, p.image, p.category_id
        FROM product_recommendations pr
        JOIN products p ON pr.product_id = p.id
        WHERE pr.user_id = ? 
        AND pr.recommendation_type = 'personalized'
        AND pr.generated_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
        ORDER BY pr.score DESC
        LIMIT ?
    ");
    $stmt->execute([$userId, $limit]);
    $cached = $stmt->fetchAll();
    
    if (!empty($cached)) {
        return $cached;
    }
    
    // Generate fresh recommendations
    generate_personalized_recommendations($userId);
    
    // Try again
    $stmt->execute([$userId, $limit]);
    $recommendations = $stmt->fetchAll();
    
    return $recommendations;
}

/**
 * Generate personalized recommendations for user
 */
function generate_personalized_recommendations($userId) {
    global $db;
    
    // Get user's purchase history
    $stmt = $db->prepare("
        SELECT DISTINCT product_id FROM product_interactions 
        WHERE user_id = ? AND interaction_type = 'purchase'
    ");
    $stmt->execute([$userId]);
    $purchased = $stmt->fetchAll();
    
    if (empty($purchased)) {
        // No history - use browsing-based recommendations
        $recommendations = get_browsing_based_recommendations($userId, 20);
    } else {
        // Get categories of purchased products
        $productIds = array_column($purchased, 'product_id');
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        
        $stmt = $db->prepare("
            SELECT DISTINCT category_id FROM products 
            WHERE id IN ($placeholders)
        ");
        $stmt->execute($productIds);
        $categories = $stmt->fetchAll();
        
        if (empty($categories)) {
            $recommendations = get_browsing_based_recommendations($userId, 20);
        } else {
            $categoryIds = array_column($categories, 'category_id');
            
            // Get products from same categories
            $catPlaceholders = implode(',', array_fill(0, count($categoryIds), '?'));
            $prodPlaceholders = implode(',', array_fill(0, count($productIds), '?'));
            
            $sql = "
                SELECT p.id, p.name, p.price, p.image, p.category_id,
                    COUNT(pi.id) as interaction_count,
                    AVG(CASE WHEN pi.rating IS NOT NULL THEN pi.rating ELSE 0 END) as avg_rating
                FROM products p
                LEFT JOIN product_interactions pi ON p.id = pi.product_id
                WHERE p.category_id IN ($catPlaceholders)
                AND p.id NOT IN ($prodPlaceholders)
                AND p.is_active = 1
                GROUP BY p.id
                ORDER BY interaction_count DESC, avg_rating DESC
                LIMIT 20
            ";
            
            $stmt = $db->prepare($sql);
            $allParams = array_merge($categoryIds, $productIds);
            $stmt->execute($allParams);
            $recommendations = $stmt->fetchAll();
        }
    }
    
    // Add browsing-based diversity
    $browsingRecs = get_browsing_based_recommendations($userId, 10);
    $existingIds = array_column($recommendations, 'id');
    
    foreach ($browsingRecs as $rec) {
        if (!in_array($rec['id'], $existingIds) && count($recommendations) < 25) {
            $recommendations[] = $rec;
            $existingIds[] = $rec['id'];
        }
    }
    
    // Format and score
    $scored = [];
    $score = 1.0;
    
    foreach ($recommendations as $rec) {
        $scored[] = [
            'product_id' => $rec['id'],
            'name' => $rec['name'],
            'price' => $rec['price'],
            'image' => $rec['image'],
            'category_id' => $rec['category_id'] ?? null,
            'score' => $score,
            'recommendation_type' => 'personalized',
        ];
        $score -= 0.05;
    }
    
    // Cache recommendations
    cache_recommendations($userId, $scored, 'personalized');
    
    return $scored;
}

/**
 * Get similar products
 */
function get_similar_products($productId, $limit = 6) {
    global $db;
    
    // Check precomputed similar products
    $stmt = $db->prepare("
        SELECT sp.similarity_score, p.id, p.name, p.price, p.image, p.category_id
        FROM similar_products sp
        JOIN products p ON sp.product_b_id = p.id
        WHERE sp.product_a_id = ?
        ORDER BY sp.similarity_score DESC
        LIMIT ?
    ");
    $stmt->execute([$productId, $limit]);
    $similar = $stmt->fetchAll();
    
    if (!empty($similar)) {
        return $similar;
    }
    
    // Compute similar products on the fly
    return compute_similar_products($productId, $limit);
}

/**
 * Compute similar products based on category and interactions
 */
function compute_similar_products($productId, $limit = 6) {
    global $db;
    
    // Get the product's category
    $stmt = $db->prepare("SELECT category_id FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        return [];
    }
    
    // Get products from same category
    $stmt = $db->prepare("
        SELECT p.id, p.name, p.price, p.image, p.category_id,
            COUNT(pi.id) as common_interactions,
            0.5 as similarity_score
        FROM products p
        LEFT JOIN product_interactions pi ON p.id = pi.product_id 
            AND pi.user_id IN (
                SELECT DISTINCT user_id FROM product_interactions 
                WHERE product_id = ? AND user_id IS NOT NULL
            )
        WHERE p.category_id = ? AND p.id != ? AND p.is_active = 1
        GROUP BY p.id
        ORDER BY common_interactions DESC
        LIMIT ?
    ");
    $stmt->execute([$productId, $product['category_id'], $productId, $limit]);
    $similar = $stmt->fetchAll();
    
    return $similar;
}

/**
 * Get frequently bought together
 */
function get_frequently_bought_together($productId, $limit = 6) {
    global $db;
    
    // Find orders that contain this product
    $stmt = $db->prepare("
        SELECT DISTINCT oi.order_id FROM order_items oi
        WHERE oi.product_id = ?
    ");
    $stmt->execute([$productId]);
    $orders = $stmt->fetchAll();
    
    if (empty($orders)) {
        return get_similar_products($productId, $limit);
    }
    
    $orderIds = array_column($orders, 'order_id');
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    
    // Find other products in same orders
    $stmt = $db->prepare("
        SELECT oi.product_id, COUNT(*) as co_occurrence,
            p.name, p.price, p.image
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id IN ($placeholders)
        AND oi.product_id != ?
        AND p.is_active = 1
        GROUP BY oi.product_id
        ORDER BY co_occurrence DESC
        LIMIT ?
    ");
    
    $allParams = array_merge($orderIds, [$productId, $limit]);
    $stmt->execute($allParams);
    $products = $stmt->fetchAll();
    
    return $products;
}

/**
 * Get popular products
 */
function get_popular_products($userId = null, $limit = 10) {
    global $db;
    
    $stmt = $db->prepare("
        SELECT p.id, p.name, p.price, p.image, p.category_id,
            COUNT(pi.id) as view_count,
            SUM(CASE WHEN pi.interaction_type = 'purchase' THEN 1 ELSE 0 END) as purchase_count,
            AVG(CASE WHEN pi.rating IS NOT NULL THEN pi.rating ELSE 0 END) as avg_rating
        FROM products p
        LEFT JOIN product_interactions pi ON p.id = pi.product_id
        WHERE p.is_active = 1
        GROUP BY p.id
        ORDER BY purchase_count DESC, view_count DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    
    $products = $stmt->fetchAll();
    $score = 1.0;
    
    foreach ($products as &$product) {
        $product['score'] = $score;
        $score -= 0.1;
    }
    
    // Cache if user provided
    if ($userId) {
        cache_recommendations($userId, $products, 'popular');
    }
    
    return $products;
}

/**
 * Get category-based recommendations
 */
function get_category_recommendations($categoryId, $limit = 10) {
    global $db;
    
    $stmt = $db->prepare("
        SELECT p.id, p.name, p.price, p.image, p.category_id,
            COUNT(pi.id) as interactions,
            AVG(CASE WHEN pi.rating IS NOT NULL THEN pi.rating ELSE 0 END) as avg_rating
        FROM products p
        LEFT JOIN product_interactions pi ON p.id = pi.product_id
        WHERE p.category_id = ? AND p.is_active = 1
        GROUP BY p.id
        ORDER BY interactions DESC, avg_rating DESC
        LIMIT ?
    ");
    $stmt->execute([$categoryId, $limit]);
    
    return $stmt->fetchAll();
}

/**
 * Cache recommendations
 */
function cache_recommendations($userId, $products, $type) {
    global $db;
    
    // Delete old cached recommendations
    $stmt = $db->prepare("
        DELETE FROM product_recommendations 
        WHERE user_id = ? AND recommendation_type = ?
    ");
    $stmt->execute([$userId, $type]);
    
    // Insert new recommendations
    foreach ($products as $product) {
        $stmt = $db->prepare("
            INSERT INTO product_recommendations 
            (user_id, product_id, recommendation_type, score)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $product['product_id'] ?? $product['id'], $type, $product['score']]);
    }
}

/**
 * Compute similar products (cron job)
 */
function compute_all_similar_products() {
    global $db;
    
    // Get all products
    $result = $db->query("SELECT id, category_id FROM products WHERE status = 'active'");
    $products = $result->fetchAll();
    
    foreach ($products as $product) {
        // Find users who viewed this product
        $stmt = $db->prepare("
            SELECT DISTINCT user_id FROM product_interactions 
            WHERE product_id = ? AND user_id IS NOT NULL
        ");
        $stmt->execute([$product['id']]);
        $users = $stmt->fetchAll();
        
        if (empty($users)) {
            continue;
        }
        
        $userIds = array_column($users, 'user_id');
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        
        // Find other products these users interacted with
        $stmt = $db->prepare("
            SELECT product_id, COUNT(DISTINCT user_id) as user_count
            FROM product_interactions
            WHERE user_id IN ($placeholders)
            AND product_id != ?
            GROUP BY product_id
            ORDER BY user_count DESC
            LIMIT 20
        ");
        
        $allParams = array_merge($userIds, [$product['id']]);
        $stmt->execute($allParams);
        $similar = $stmt->fetchAll();
        
        // Calculate similarity score and save
        $maxUsers = max(array_column($similar, 'user_count'));
        
        foreach ($similar as $item) {
            $score = $item['user_count'] / $maxUsers;
            
            // Check if exists
            $stmt2 = $db->prepare("
                SELECT id FROM similar_products 
                WHERE product_a_id = ? AND product_b_id = ?
            ");
            $stmt2->execute([$product['id'], $item['product_id']]);
            
            if ($stmt2->fetch()) {
                $stmt2 = $db->prepare("
                    UPDATE similar_products SET similarity_score = ? 
                    WHERE product_a_id = ? AND product_b_id = ?
                ");
                $stmt2->execute([$score, $product['id'], $item['product_id']]);
            } else {
                $stmt2 = $db->prepare("
                    INSERT INTO similar_products (product_a_id, product_b_id, similarity_score)
                    VALUES (?, ?, ?)
                ");
                $stmt2->execute([$product['id'], $item['product_id'], $score]);
            }
        }
    }
    
    return ['success' => true, 'message' => 'Similar products computed'];
}

/**
 * Get recommendations for API - Enhanced with new types
 */
function get_recommendations_api($userId, $type = 'personalized', $limit = 10, $productId = null, $categoryId = null) {
    switch ($type) {
        case 'personalized':
            return get_personalized_recommendations($userId, $limit);
            
        case 'popular':
            return get_popular_products($userId, $limit);
            
        case 'similar':
            if ($productId) {
                return get_similar_products($productId, $limit);
            }
            return [];
            
        case 'frequently_bought':
            if ($productId) {
                return get_frequently_bought_together($productId, $limit);
            }
            return [];
            
        case 'frequently_viewed':
            if ($productId) {
                return get_frequently_viewed_together($productId, $limit);
            }
            return [];
            
        case 'browsing_based':
            if ($userId) {
                return get_browsing_based_recommendations($userId, $limit);
            }
            return get_popular_products(null, $limit);
            
        case 'trending':
            return get_trending_products($limit);
            
        case 'category':
            if ($categoryId) {
                return get_category_recommendations($categoryId, $limit);
            }
            return [];
            
        default:
            return get_personalized_recommendations($userId, $limit);
    }
}
