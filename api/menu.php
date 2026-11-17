<?php
require_once __DIR__ . "/_bootstrap.php";
api_run(function (): void {
    api_method(["GET"]);
    $categories = all_rows("SELECT category_id, name, description FROM categories WHERE is_active = 1 ORDER BY sort_order, name");
    foreach ($categories as &$category) {
        $category["products"] = all_rows("SELECT product_id, name, description, price, photo, stock_quantity, is_available FROM products WHERE category_id = ? AND is_active = 1 ORDER BY name", "i", [$category["category_id"]]);
        foreach ($category["products"] as &$product) {
            $product["option_groups"] = all_rows("SELECT group_id, name, is_required FROM option_groups WHERE category_id = ? AND is_active = 1 ORDER BY group_id", "i", [$category["category_id"]]);
            foreach ($product["option_groups"] as &$group) {
                $group["values"] = all_rows("SELECT value_id, label, extra_price FROM option_values WHERE group_id = ? AND is_active = 1 ORDER BY value_id", "i", [$group["group_id"]]);
            }
            unset($group);
        }
        unset($product);
    }
    unset($category);
    json_response(["categories" => $categories]);
});
