<?php

require_once __DIR__ . '/../helpers.php';


requireLogin();

$canManageProducts = false;

if (
    $_SESSION['role'] == 'admin' ||
    $_SESSION['role'] == 'inventory_manager'
) {
    $canManageProducts = true;
}

$errorMessage = '';
$editProduct = null;


if ($_SERVER['REQUEST_METHOD'] == 'POST') {

   
    requireRole(array('admin', 'inventory_manager'));

    $productId = (int) $_POST['id'];


    if ($_POST['action'] == 'toggle') {

        $sql = "UPDATE products
                SET is_active = NOT is_active
                WHERE id = :id";

        $statement = $pdo->prepare($sql);

        $statement->execute(array('id' => $productId));

        writeAudit(
            $pdo,
            'toggle_product',
            'Product id ' . $productId
        );

        header('Location: products.php');
        exit;
    }


    $sku = trim($_POST['sku']);
    $productName = trim($_POST['name']);
    $category = trim($_POST['category']);
    $unit = trim($_POST['unit']);
    $minimumStock = $_POST['minimum_stock'];


    if (
        $sku == '' ||
        $productName == '' ||
        $category == '' ||
        $unit == ''
    ) {

        $errorMessage = 'All fields are required.';

    } elseif (
        !is_numeric($minimumStock) ||
        $minimumStock < 0
    ) {

        $errorMessage = 'Minimum stock must be 0 or more.';

    } else {

        try {

           
            $productData = array(
                'sku' => $sku,
                'name' => $productName,
                'category' => $category,
                'unit' => $unit,
                'min' => $minimumStock
            );


            if ($productId > 0) {

                $productData['id'] = $productId;

                $sql = "UPDATE products
                        SET sku = :sku,
                            name = :name,
                            category = :category,
                            unit = :unit,
                            minimum_stock = :min
                        WHERE id = :id";

            } else {

             
                $sql = "INSERT INTO products
                        (sku, name, category, unit, minimum_stock)

                        VALUES
                        (:sku, :name, :category, :unit, :min)";
            }


            
            $statement = $pdo->prepare($sql);

            $statement->execute($productData);


          
            writeAudit(
                $pdo,
                'save_product',
                'SKU ' . $sku
            );

            header('Location: products.php');
            exit;

        } catch (PDOException $exception) {

            $errorMessage = 'This SKU already exists.';
        }
    }


    $editProduct = $_POST;
}


if ($editProduct === null && isset($_GET['edit'])) {

    $productId = (int) $_GET['edit'];

    $sql = "SELECT *
            FROM products
            WHERE id = :id";

    $statement = $pdo->prepare($sql);

    $statement->execute(array('id' => $productId));

    $editProduct = $statement->fetch();
}


$searchText = '';

if (isset($_GET['search'])) {
    $searchText = trim($_GET['search']);
}


$sql = "SELECT products.*,

        (
            SELECT COALESCE(SUM(quantity_remaining), 0)
            FROM batches
            WHERE product_id = products.id
        ) AS stock

        FROM products

        WHERE products.name LIKE :searchName
        OR products.sku LIKE :searchSku

        ORDER BY products.name";

$statement = $pdo->prepare($sql);

$statement->execute(array(
    'searchName' => '%' . $searchText . '%',
    'searchSku' => '%' . $searchText . '%'
));

$products = $statement->fetchAll();


pageHeader('Products');

?>

<h2>Products</h2>


<?php


if ($errorMessage != '') {

    echo '<p>' . e($errorMessage) . '</p>';
}

?>



<form method="GET">

    <input
        type="text"
        name="search"
        placeholder="Name or SKU"
        value="<?php echo e($searchText); ?>"
    >

    <button type="submit">Search</button>

    <a href="products.php">Clear</a>

</form>

<br>




<table border="1" cellpadding="5">

    <tr>
        <th>SKU</th>
        <th>Name</th>
        <th>Category</th>
        <th>Unit</th>
        <th>Min</th>
        <th>Stock</th>
        <th>Status</th>
        <th>Actions</th>
    </tr>


    <?php

    foreach ($products as $product) {

    ?>

        <tr>

            <td>
                <?php echo e($product['sku']); ?>
            </td>

            <td>
                <?php echo e($product['name']); ?>
            </td>

            <td>
                <?php echo e($product['category']); ?>
            </td>

            <td>
                <?php echo e($product['unit']); ?>
            </td>

            <td>
                <?php echo e($product['minimum_stock']); ?>
            </td>

            <td>
                <?php echo e($product['stock']); ?>
            </td>

            <td>

                <?php

                if ($product['is_active']) {

                    echo 'Active';

                } else {

                    echo 'Inactive';
                }

                ?>

            </td>

            <td>

                <?php

                if ($canManageProducts) {

                ?>

                    <a href="products.php?edit=<?php echo e($product['id']); ?>">
                        Edit
                    </a>


                    <form method="POST" style="display:inline">

                        <input
                            type="hidden"
                            name="action"
                            value="toggle"
                        >

                        <input
                            type="hidden"
                            name="id"
                            value="<?php echo e($product['id']); ?>"
                        >


                        <?php

                        if ($product['is_active']) {

                            echo '<button type="submit">Deactivate</button>';

                        } else {

                            echo '<button type="submit">Activate</button>';
                        }

                        ?>

                    </form>

                <?php
                }
                ?>

            </td>

        </tr>

    <?php
    }
    ?>

</table>


<?php


if ($canManageProducts) {

    ?>

    <h3>

        <?php

        if ($editProduct) {

            echo 'Edit product';

        } else {

            echo 'Add product';
        }

        ?>

    </h3>


    <form method="POST">

        <input
            type="hidden"
            name="action"
            value="save"
        >


        <?php

        $formProductId = 0;
        $formSku = '';
        $formName = '';
        $formCategory = '';
        $formUnit = '';
        $formMinimumStock = 0;

        if ($editProduct) {

            if (isset($editProduct['id'])) {
                $formProductId = $editProduct['id'];
            }

            if (isset($editProduct['sku'])) {
                $formSku = $editProduct['sku'];
            }

            if (isset($editProduct['name'])) {
                $formName = $editProduct['name'];
            }

            if (isset($editProduct['category'])) {
                $formCategory = $editProduct['category'];
            }

            if (isset($editProduct['unit'])) {
                $formUnit = $editProduct['unit'];
            }

            if (isset($editProduct['minimum_stock'])) {
                $formMinimumStock = $editProduct['minimum_stock'];
            }
        }

        ?>


        <input
            type="hidden"
            name="id"
            value="<?php echo e($formProductId); ?>"
        >


        SKU:

        <input
            type="text"
            name="sku"
            value="<?php echo e($formSku); ?>"
            required
        >

        <br><br>


        Name:

        <input
            type="text"
            name="name"
            value="<?php echo e($formName); ?>"
            required
        >


        <br><br>


        Category:

        <input
            type="text"
            name="category"
            value="<?php echo e($formCategory); ?>"
            required
        >


        <br><br>


        Unit (tablet, box...):

        <input
            type="text"
            name="unit"
            value="<?php echo e($formUnit); ?>"
            required
        >

        <br><br>


        Minimum stock:

        <input
            type="number"
            name="minimum_stock"
            min="0"
            step="0.001"
            value="<?php echo e($formMinimumStock); ?>"
            required
        >

        <br><br>


        <button type="submit">Save</button>

    </form>

<?php
}

?>

<?php

pageFooter();

?>
