  $stmt_items = $conn->prepare("INSERT INTO purchase_items 
            (purchase_id, product_name, product_unit, product_quantity, product_price, product_discount, product_total) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($products_data as $product) {
            $stmt_items->bind_param(
                "issdddd",
                $purchase_id,
                $product['name'],
                $product['unit'],
                $product['quantity'],
                $product['price'],
                $product['discount'],
                $product['total']
            );
        foreach ($products_data as $index => $product) {
            $stmt_items->bind_param(
                "issddddd",
                $purchase_id,
                $product['name'],
                $product['unit'],
                $product['quantity'],
                $product['price'],
                $product['discount'],
                $product['total']
            );
            
            if (!$stmt_items->execute()) {
                throw new Exception("خطأ في حفظ أصناف المشتريات: " . $stmt_items->error);
            }
