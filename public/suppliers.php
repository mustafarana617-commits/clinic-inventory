<?php

require_once __DIR__ . '/../helpers.php';


requireRole(array('admin', 'inventory_manager'));

$errorMessage = '';


if ($_SERVER['REQUEST_METHOD'] == 'POST') {


    
    if ($_POST['action'] == 'toggle') {

        $supplierId = (int) $_POST['id'];

        $sql = "UPDATE suppliers
                SET is_active = NOT is_active
                WHERE id = :id";

        $statement = $pdo->prepare($sql);

        $statement->execute(array('id' => $supplierId));


      
        writeAudit(
            $pdo,
            'toggle_supplier',
            'Supplier id ' . $supplierId
        );


        header('Location: suppliers.php');
        exit;
    }


    $supplierName = trim($_POST['name']);
    $supplierEmail = trim($_POST['email']);

    if ($supplierName == '') {

        $errorMessage = 'Supplier name is required.';

    }

    elseif (
        $supplierEmail != '' &&
        !filter_var($supplierEmail, FILTER_VALIDATE_EMAIL)
    ) {

        $errorMessage = 'Invalid email address.';

    } else {

       
        $supplierPhone = trim($_POST['phone']);


  
        $sql = "INSERT INTO suppliers
                (name, phone, email)

                VALUES
                (:name, :phone, :email)";

        $statement = $pdo->prepare($sql);

        $statement->execute(array(
            'name' => $supplierName,
            'phone' => $supplierPhone,
            'email' => $supplierEmail
        ));


        writeAudit(
            $pdo,
            'add_supplier',
            $supplierName
        );


        
        header('Location: suppliers.php');
        exit;
    }
}


$sql = "SELECT *
        FROM suppliers
        ORDER BY name";

$statement = $pdo->query($sql);

$suppliers = $statement->fetchAll();


pageHeader('Suppliers');

?>

<h2>Suppliers</h2>


<?php

if ($errorMessage != '') {

    echo '<p>' . e($errorMessage) . '</p>';
}

?>



<table border="1" cellpadding="5">

    <tr>
        <th>Name</th>
        <th>Phone</th>
        <th>Email</th>
        <th>Status</th>
        <th>Actions</th>
    </tr>


    <?php

    foreach ($suppliers as $supplier) {

    ?>

        <tr>

            <td>
                <?php echo e($supplier['name']); ?>
            </td>

            <td>
                <?php echo e($supplier['phone']); ?>
            </td>

            <td>
                <?php echo e($supplier['email']); ?>
            </td>

            <td>

                <?php

                if ($supplier['is_active']) {

                    echo 'Active';

                } else {

                    echo 'Inactive';
                }

                ?>

            </td>

            <td>

            

                <form method="POST">

                    <input
                        type="hidden"
                        name="action"
                        value="toggle"
                    >

                    <input
                        type="hidden"
                        name="id"
                        value="<?php echo e($supplier['id']); ?>"
                    >


                    <?php

                    if ($supplier['is_active']) {

                        echo '<button type="submit">Deactivate</button>';

                    } else {

                        echo '<button type="submit">Activate</button>';
                    }

                    ?>

                </form>

            </td>

        </tr>

    <?php
    }
    ?>

</table>



<h3>Add supplier</h3>

<form method="POST">

    <input
        type="hidden"
        name="action"
        value="save"
    >


    Name:

    <input
        type="text"
        name="name"
        required
    >

    <br><br>


    Phone:

    <input
        type="text"
        name="phone"
    >

    <br><br>


    Email:

    <input
        type="email"
        name="email"
    >

    <br><br>


    <button type="submit">Add</button>

</form>


<?php

pageFooter();

?>
