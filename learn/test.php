<?php




?>
<!DOCTYPE html>

<html class="dark" dir="rtl" lang="ar"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>سجل المخزون والأصناف</title>
</head>
<body class="bg-background-light dark:bg-background-dark text-[#111418] dark:text-gray-100 min-h-screen">
    
        <button type="button" onclick="add_row()">اضافة منتج</button>

<table id="table_prodect">
    <thead>
      <tr>
        <td>#</td>
        <td>اسم المنتج</td>
        <td>الوحدة</td>
        <td>الكمية</td>
      </tr>  
    </thead>
    <tbody id="body_table_prodect" border="1">
        <tr id="row0">
            <td>0</td>
            <td> <input type="text" name="products[0][product_name]"  placeholder="ابحث عن منتج ..."></td>
            <td><select name="products[0][product_unit]" id="unite_prodect"><option value="">اختار اسم المنتج اولا</option></select></td>
            <td><input type="number" name="products[0][product_quantity]"></td>
            <td><button onclick="delete_row(0)">🗑️</button></td>
        </tr>
    </tbody>
</table>

<script>
    function add_row(){
        
        var table=document.getElementById('table_prodect');
        number_row=table.rows.length-1;
        var newRow=table.insertRow();
        var cells=newRow.insertCell(0);
        cells.innerHTML=number_row;
        cells=newRow.insertCell(1);
        cells.innerHTML='<input type="text" name="products['+number_row+'][product_name]"  placeholder="ابحث عن منتج ...">';
        cells=newRow.insertCell(2);
        cells.innerHTML='<select name="products['+number_row+'][product_unit]" id="unite_prodect"><option value="">اختار اسم المنتج اولا</option></select>';
        cells=newRow.insertCell(3);
        cells.innerHTML='<input type="number" name="products['+number_row+'][product_quantity]">';
        cells=newRow.insertCell(4);
        cells.innerHTML='<button onclick="delete_row('+number_row+')">🗑️</button>';
    }
    function delete_row(id){
        var row=document.getElementById('row'+id);
        if(confirm("هل انت متاكد من حذف هذا المنتج؟ "+id)){
        row.remove();
    }
}
</script>
</body>
</html>