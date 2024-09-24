<?php
class Inventory
{
	private $host  = 'localhost';
	private $user  = 'root';
	private $password   = '';
	private $database  = 'ims_db';
	private $userTable = 'ims_user';
	private $customerTable = 'ims_customer';
	private $categoryTable = 'ims_category';
	private $brandTable = 'ims_brand';
	private $productTable = 'ims_product';
	private $supplierTable = 'ims_supplier';
	private $purchaseTable = 'ims_purchase';
	private $orderTable = 'ims_order';
	private $dbConnect = false;
	private $conn;

	public function __construct()
	{
		if (!$this->dbConnect) {
			$this->conn = new mysqli($this->host, $this->user, $this->password, $this->database);
			if ($this->conn->connect_error) {
				die("Error failed to connect to MySQL: " . $this->conn->connect_error);
			} else {
				$this->dbConnect = $this->conn;
			}
		}
	}

	private function getData($sqlQuery)
	{
		$result = mysqli_query($this->dbConnect, $sqlQuery);
		if (!$result) {
			die('An unexpected error occurred. Please try again later.');
		}
		$data = array();
		while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
			$data[] = $row;
		}
		return $data;
	}

	private function getNumRows(string $sqlQuery, array $params = [], string $types = ''): int
	{
		if ($stmt = mysqli_prepare($this->dbConnect, $sqlQuery)) {
			if (!empty($params)) {
				mysqli_stmt_bind_param($stmt, $types, ...$params);
			}
			mysqli_stmt_execute($stmt);
			$result = mysqli_stmt_get_result($stmt);
			if (!$result) {
				die('An unexpected error occurred. Please try again later.');
			}
			$numRows = mysqli_num_rows($result);
			mysqli_free_result($result);
			mysqli_stmt_close($stmt);

			return $numRows;
		} else {
			die('An unexpected error occurred. Please try again later.');
		}
	}



	public function login($email, $password)
	{
		$password = md5($password);
		$sqlQuery = "
			SELECT userid, email, password, name, type, status
			FROM " . $this->userTable . " 
			WHERE email = ? AND password = ?";

		$stmt = $this->conn->prepare($sqlQuery);
		$stmt->bind_param('ss', $email, $password);
		$stmt->execute();

		$result = $stmt->get_result();
		return $result->fetch_all(MYSQLI_ASSOC);
	}


	public function checkLogin()
	{
		if (empty($_SESSION['userid'])) {
			header("Location:login.php");
		}
	}
	public function getCustomer()
	{
		$sqlQuery = "SELECT * FROM " . $this->customerTable . " WHERE id = ?";

		if ($stmt = mysqli_prepare($this->dbConnect, $sqlQuery)) {
			mysqli_stmt_bind_param($stmt, 'i', $_POST["userid"]);
			mysqli_stmt_execute($stmt);
			$result = mysqli_stmt_get_result($stmt);
			$row = mysqli_fetch_array($result, MYSQLI_ASSOC);
			mysqli_stmt_close($stmt);
			echo json_encode($row);
		} else {
			// Show a generic message to the user
			echo 'An unexpected error occurred. Please try again later.';
		}
	}

	public function getCustomerList()
	{
		$sqlQuery = "SELECT * FROM " . $this->customerTable;
		$queryParams = [];
		$queryConditions = [];

		// Handle search input securely
		if (!empty($_POST["search"]["value"])) {
			$searchValue = '%' . $_POST["search"]["value"] . '%';
			$queryConditions[] = '(id LIKE ? OR name LIKE ? OR address LIKE ? OR mobile LIKE ? OR balance LIKE ?)';
			$queryParams[] = $searchValue;
			$queryParams[] = $searchValue;
			$queryParams[] = $searchValue;
			$queryParams[] = $searchValue;
			$queryParams[] = $searchValue;
		}

		// Append WHERE clause if there are conditions
		if (count($queryConditions) > 0) {
			$sqlQuery .= ' WHERE ' . implode(' AND ', $queryConditions);
		}

		// Handle ordering
		if (!empty($_POST["order"])) {
			$columnIndex = intval($_POST['order']['0']['column']);
			$columnOrder = $_POST['order']['0']['dir'] === 'asc' ? 'ASC' : 'DESC';
			$sqlQuery .= ' ORDER BY ' . $this->getColumnByIndex($columnIndex) . ' ' . $columnOrder;
		} else {
			$sqlQuery .= ' ORDER BY id DESC';
		}

		// Handle pagination
		if ($_POST["length"] != -1) {
			$sqlQuery .= ' LIMIT ?, ?';
			$queryParams[] = intval($_POST['start']);
			$queryParams[] = intval($_POST['length']);
		}

		// Prepare the query
		if ($stmt = mysqli_prepare($this->dbConnect, $sqlQuery)) {
			// Dynamically bind the parameters
			if (count($queryParams) > 0) {
				$types = str_repeat('s', count($queryParams) - 2) . 'ii';  // 's' for string, 'i' for integers
				mysqli_stmt_bind_param($stmt, $types, ...$queryParams);
			}

			// Execute the statement and fetch the result
			mysqli_stmt_execute($stmt);
			$result = mysqli_stmt_get_result($stmt);
			$numRows = mysqli_num_rows($result);

			// Process the result into the customerData array
			$customerData = [];
			while ($customer = mysqli_fetch_assoc($result)) {
				$customerRows = [
					$customer['id'],
					$customer['name'],
					$customer['address'],
					$customer['mobile'],
					number_format($customer['balance'], 2),
					'<button type="button" name="update" id="' . $customer["id"] . '" class="btn btn-primary btn-sm rounded-0 update" title="update"><i class="fa fa-edit"></i></button>
					<button type="button" name="delete" id="' . $customer["id"] . '" class="btn btn-danger btn-sm rounded-0 delete"><i class="fa fa-trash"></i></button>',
					''
				];
				$customerData[] = $customerRows;
			}

			// Return the final output as JSON
			$output = [
				"draw" => intval($_POST["draw"]),
				"recordsTotal" => $numRows,
				"recordsFiltered" => $numRows,
				"data" => $customerData
			];

			// Close the statement
			mysqli_stmt_close($stmt);
			echo json_encode($output);
		} else {
			// Handle errors
			echo json_encode(['error' => 'An unexpected error occurred. Please try again later.']);
		}
	}


	private function getColumnByIndex($index)
	{
		$columns = ['id', 'name', 'address', 'mobile', 'balance'];
		return isset($columns[$index]) ? $columns[$index] : 'id';
	}
	public function saveCustomer()
	{
		// Sanitize inputs to handle nulls or unexpected values
		$name = $_POST['cname'] ?? '';
		$address = $_POST['address'] ?? '';
		$mobile = $_POST['mobile'] ?? '';
		$balance = $_POST['balance'] ?? 0.0;  // Default to 0.0 if not provided

		// SQL query with placeholders
		$sqlInsert = "
			INSERT INTO " . $this->customerTable . " (name, address, mobile, balance) 
			VALUES (?, ?, ?, ?)";

		// Prepare the statement
		if ($stmt = mysqli_prepare($this->dbConnect, $sqlInsert)) {
			// Bind parameters (s = string, d = double/float)
			mysqli_stmt_bind_param($stmt, "sssd", $name, $address, $mobile, $balance);

			// Execute the statement
			if (mysqli_stmt_execute($stmt)) {
				echo 'New Customer Added';
			} else {
				// Show a generic message to the user
				echo 'An unexpected error occurred. Please try again later.';
			}

			// Close the statement
			mysqli_stmt_close($stmt);
		} else {
			// Show a generic message to the user
			echo 'An unexpected error occurred. Please try again later.';
		}
	}


	public function updateCustomer()
	{
		if (isset($_POST['userid'])) {
			$userid = $_POST['userid'] ?? 0;
			$name = $_POST['cname'] ?? '';
			$address = $_POST['address'] ?? '';
			$mobile = $_POST['mobile'] ?? '';
			$balance = $_POST['balance'] ?? 0.0;

			$sqlUpdate = "
				UPDATE " . $this->customerTable . " 
				SET name = ?, address = ?, mobile = ?, balance = ?
				WHERE id = ?";

			if ($stmt = mysqli_prepare($this->dbConnect, $sqlUpdate)) {
				mysqli_stmt_bind_param($stmt, "sssdi", $name, $address, $mobile, $balance, $userid);
				if (mysqli_stmt_execute($stmt)) {
					echo 'Customer Edited';
				} else {
					// Show a generic message to the user
					echo 'An unexpected error occurred. Please try again later.';
				}
				mysqli_stmt_close($stmt);
			} else {
				// Show a generic message to the user
				echo 'An unexpected error occurred. Please try again later.';
			}
		} else {
			echo 'No user ID provided';
		}
	}

	public function deleteCustomer()
	{
		if (isset($_POST['userid'])) {
			$userid = $_POST['userid'] ?? 0;

			$sqlQuery = "
				DELETE FROM " . $this->customerTable . " 
				WHERE id = ?";

			if ($stmt = mysqli_prepare($this->dbConnect, $sqlQuery)) {
				mysqli_stmt_bind_param($stmt, "i", $userid);
				if (mysqli_stmt_execute($stmt)) {
					echo 'Customer Deleted';
				} else {
					// Show a generic message to the user
					echo 'An unexpected error occurred. Please try again later.';
				}
				mysqli_stmt_close($stmt);
			} else {
				// Show a generic message to the user
				echo 'An unexpected error occurred. Please try again later.';
			}
		} else {
			echo 'No user ID provided';
		}
	}

	// Category functions
	public function getCategoryList()
	{
		$sqlQuery = "SELECT * FROM " . $this->categoryTable;
		$queryParams = [];
		$queryConditions = [];

		// Handle search input securely
		if (!empty($_POST["search"]["value"])) {
			$searchValue = '%' . $_POST["search"]["value"] . '%';
			$queryConditions[] = '(name LIKE ? OR status LIKE ?)';
			$queryParams[] = $searchValue;
			$queryParams[] = $searchValue;
		}

		// Append WHERE clause if there are conditions
		if (count($queryConditions) > 0) {
			$sqlQuery .= ' WHERE ' . implode(' AND ', $queryConditions);
		}

		// Handle ordering
		if (!empty($_POST["order"])) {
			$columnIndex = intval($_POST['order']['0']['column']);
			$columnOrder = $_POST['order']['0']['dir'] === 'asc' ? 'ASC' : 'DESC';
			$sqlQuery .= ' ORDER BY ' . $this->getColumnByIndex($columnIndex) . ' ' . $columnOrder;
		} else {
			$sqlQuery .= ' ORDER BY categoryid DESC';
		}

		// Handle pagination
		if ($_POST["length"] != -1) {
			$sqlQuery .= ' LIMIT ?, ?';
			$queryParams[] = intval($_POST['start']);
			$queryParams[] = intval($_POST['length']);
		}

		// Prepare the query
		if ($stmt = mysqli_prepare($this->dbConnect, $sqlQuery)) {
			// Dynamically bind the parameters
			if (count($queryParams) > 0) {
				$types = str_repeat('s', count($queryParams) - 2) . 'ii';  // 's' for string, 'i' for integers
				mysqli_stmt_bind_param($stmt, $types, ...$queryParams);
			}

			// Execute the statement and fetch the result
			mysqli_stmt_execute($stmt);
			$result = mysqli_stmt_get_result($stmt);
			$numRows = mysqli_num_rows($result);

			// Process the result into the categoryData array
			$categoryData = [];
			while ($category = mysqli_fetch_assoc($result)) {
				$categoryRows = [];
				$status = $category['status'] == 'active'
					? '<span class="label label-success">Active</span>'
					: '<span class="label label-danger">Inactive</span>';

				$categoryRows[] = $category['categoryid'];
				$categoryRows[] = $category['name'];
				$categoryRows[] = $status;
				$categoryRows[] = '<button type="button" name="update" id="' . $category["categoryid"] . '" class="btn btn-primary btn-sm rounded-0 update" title="Update"><i class="fa fa-edit"></i></button><button type="button" name="delete" id="' . $category["categoryid"] . '" class="btn btn-danger btn-sm rounded-0 delete" title="Delete"><i class="fa fa-trash"></i></button>';

				$categoryData[] = $categoryRows;
			}

			// Return the final output as JSON
			$output = [
				"draw" => intval($_POST["draw"]),
				"recordsTotal" => $numRows,
				"recordsFiltered" => $numRows,
				"data" => $categoryData
			];

			// Close the statement
			mysqli_stmt_close($stmt);
			echo json_encode($output);
		} else {
			// Handle errors
			echo json_encode(['error' => 'An unexpected error occurred. Please try again later.']);
		}
	}


	public function saveCategory()
	{
		$category = $_POST['category'] ?? '';

		$sqlInsert = "
			INSERT INTO " . $this->categoryTable . " (name) 
			VALUES (?)";

		if ($stmt = mysqli_prepare($this->dbConnect, $sqlInsert)) {
			mysqli_stmt_bind_param($stmt, "s", $category);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
			echo 'New Category Added';
		} else {
			echo 'Database error';
		}
	}

	public function getCategory()
	{
		$categoryId = $_POST["categoryId"] ?? '';

		$sqlQuery = "
			SELECT * FROM " . $this->categoryTable . " 
			WHERE categoryid = ?";

		if ($stmt = mysqli_prepare($this->dbConnect, $sqlQuery)) {
			mysqli_stmt_bind_param($stmt, "i", $categoryId);
			mysqli_stmt_execute($stmt);
			$result = mysqli_stmt_get_result($stmt);
			$row = mysqli_fetch_array($result, MYSQLI_ASSOC);
			mysqli_stmt_close($stmt);
			echo json_encode($row);
		} else {
			echo 'Database error';
		}
	}

	public function updateCategory()
	{
		$category = $_POST['category'] ?? '';
		$categoryId = $_POST["categoryId"] ?? '';

		if (!empty($categoryId)) {
			$sqlUpdate = "
				UPDATE " . $this->categoryTable . " 
				SET name = ? 
				WHERE categoryid = ?";

			if ($stmt = mysqli_prepare($this->dbConnect, $sqlUpdate)) {
				mysqli_stmt_bind_param($stmt, "si", $category, $categoryId);
				mysqli_stmt_execute($stmt);
				mysqli_stmt_close($stmt);
				echo 'Category Updated';
			} else {
				echo 'Database error';
			}
		} else {
			echo 'Invalid category ID';
		}
	}

	public function deleteCategory()
	{
		$categoryId = $_POST["categoryId"] ?? '';

		if (!empty($categoryId)) {
			$sqlQuery = "
				DELETE FROM " . $this->categoryTable . " 
				WHERE categoryid = ?";

			if ($stmt = mysqli_prepare($this->dbConnect, $sqlQuery)) {
				mysqli_stmt_bind_param($stmt, "i", $categoryId);
				mysqli_stmt_execute($stmt);
				mysqli_stmt_close($stmt);
				echo 'Category Deleted';
			} else {
				echo 'Database error';
			}
		} else {
			echo 'Invalid category ID';
		}
	}

	// Brand management 
	public function getBrandList()
	{
		$searchValue = $_POST["search"]["value"] ?? '';
		$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
		$length = isset($_POST['length']) ? intval($_POST['length']) : 10;
		$orderColumnIndex = isset($_POST['order']['0']['column']) ? intval($_POST['order']['0']['column']) : 0;
		$orderDir = isset($_POST['order']['0']['dir']) ? $_POST['order']['0']['dir'] : 'DESC';

		$columns = ['b.id', 'b.bname', 'c.name', 'b.status']; // Map column index to actual column names
		$orderColumn = isset($columns[$orderColumnIndex]) ? $columns[$orderColumnIndex] : 'b.id';

		$sqlQuery = "
			SELECT b.id, b.bname, c.name AS categoryName, b.status
			FROM " . $this->brandTable . " AS b
			INNER JOIN " . $this->categoryTable . " AS c ON c.categoryid = b.categoryid";

		$queryConditions = [];
		$queryParams = [];

		if (!empty($searchValue)) {
			$searchValue = '%' . $searchValue . '%';
			$queryConditions[] = 'b.bname LIKE ?';
			$queryConditions[] = 'c.name LIKE ?';
			$queryConditions[] = 'b.status LIKE ?';
			$queryParams = array_fill(0, 3, $searchValue); // Add the same search value for all conditions
		}

		if (count($queryConditions) > 0) {
			$sqlQuery .= ' WHERE ' . implode(' OR ', $queryConditions);
		}

		$sqlQuery .= ' ORDER BY ' . $orderColumn . ' ' . ($orderDir === 'asc' ? 'ASC' : 'DESC');

		if ($length != -1) {
			$sqlQuery .= ' LIMIT ?, ?';
			$queryParams[] = $start;
			$queryParams[] = $length;
		}

		if ($stmt = mysqli_prepare($this->dbConnect, $sqlQuery)) {
			if (count($queryParams) > 0) {
				$types = str_repeat('s', count($queryParams) - 2) . 'ii'; // Adjust types for parameters
				mysqli_stmt_bind_param($stmt, $types, ...$queryParams);
			}

			mysqli_stmt_execute($stmt);
			$result = mysqli_stmt_get_result($stmt);
			$numRows = mysqli_num_rows($result);

			$brandData = [];
			while ($brand = mysqli_fetch_assoc($result)) {
				$status = $brand['status'] === 'active'
					? '<span class="label label-success">Active</span>'
					: '<span class="label label-danger">Inactive</span>';

				$brandRows = [];
				$brandRows[] = $brand['id'];
				$brandRows[] = $brand['bname'];
				$brandRows[] = $brand['categoryName'];
				$brandRows[] = $status;
				$brandRows[] = '<button type="button" name="update" id="' . $brand["id"] . '" class="btn btn-primary btn-sm rounded-0 update" title="Update"><i class="fa fa-edit"></i></button><button type="button" name="delete" id="' . $brand["id"] . '" class="btn btn-danger btn-sm rounded-0 delete" data-status="' . $brand["status"] . '" title="Delete"><i class="fa fa-trash"></i></button>';
				$brandData[] = $brandRows;
			}

			mysqli_stmt_close($stmt);
		} else {
			echo json_encode(['error' => 'Database error']);
			return;
		}

		$output = [
			"draw" => intval($_POST["draw"]),
			"recordsTotal" => $numRows,
			"recordsFiltered" => $numRows,
			"data" => $brandData
		];
		echo json_encode($output);
	}

	public function categoryDropdownList()
	{
		$sqlQuery = "
			SELECT * FROM " . $this->categoryTable . " 
			WHERE status = 'active' 
			ORDER BY name ASC";

		if ($stmt = mysqli_prepare($this->dbConnect, $sqlQuery)) {
			mysqli_stmt_execute($stmt);
			$result = mysqli_stmt_get_result($stmt);
			$categoryHTML = '';
			while ($category = mysqli_fetch_assoc($result)) {
				$categoryHTML .= '<option value="' . htmlspecialchars($category["categoryid"]) . '">' . htmlspecialchars($category["name"]) . '</option>';
			}
			mysqli_stmt_close($stmt);
			return $categoryHTML;
		} else {
			return 'Error fetching categories';
		}
	}

	public function saveBrand()
	{
		$categoryid = $_POST["categoryid"] ?? '';
		$bname = $_POST['bname'] ?? '';

		$sqlInsert = "
			INSERT INTO " . $this->brandTable . " (categoryid, bname) 
			VALUES (?, ?)";

		if ($stmt = mysqli_prepare($this->dbConnect, $sqlInsert)) {
			mysqli_stmt_bind_param($stmt, "is", $categoryid, $bname);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
			echo 'New Brand Added';
		} else {
			echo 'Database error';
		}
	}

	public function getBrand()
	{
		$id = $_POST["id"] ?? '';

		$sqlQuery = "
			SELECT * FROM " . $this->brandTable . " 
			WHERE id = ?";

		if ($stmt = mysqli_prepare($this->dbConnect, $sqlQuery)) {
			mysqli_stmt_bind_param($stmt, "i", $id);
			mysqli_stmt_execute($stmt);
			$result = mysqli_stmt_get_result($stmt);
			$row = mysqli_fetch_array($result, MYSQLI_ASSOC);
			mysqli_stmt_close($stmt);
			echo json_encode($row);
		} else {
			echo 'Database error';
		}
	}

	public function updateBrand()
	{
		$id = $_POST['id'] ?? '';
		$bname = $_POST['bname'] ?? '';
		$categoryid = $_POST['categoryid'] ?? '';

		if (!empty($id)) {
			$sqlUpdate = "
				UPDATE " . $this->brandTable . " 
				SET bname = ?, categoryid = ? 
				WHERE id = ?";

			if ($stmt = mysqli_prepare($this->dbConnect, $sqlUpdate)) {
				mysqli_stmt_bind_param($stmt, "sii", $bname, $categoryid, $id);
				mysqli_stmt_execute($stmt);
				mysqli_stmt_close($stmt);
				echo 'Brand Updated';
			} else {
				echo 'Database error';
			}
		} else {
			echo 'Invalid ID';
		}
	}

	public function deleteBrand()
	{
		$id = $_POST["id"] ?? '';

		if (!empty($id)) {
			$sqlQuery = "
				DELETE FROM " . $this->brandTable . " 
				WHERE id = ?";

			if ($stmt = mysqli_prepare($this->dbConnect, $sqlQuery)) {
				mysqli_stmt_bind_param($stmt, "i", $id);
				mysqli_stmt_execute($stmt);
				mysqli_stmt_close($stmt);
				echo 'Brand Deleted';
			} else {
				echo 'Database error';
			}
		} else {
			echo 'Invalid ID';
		}
	}

	// Product management 
	public function getProductList()
	{
		// Base SQL query
		$sqlQuery = "SELECT * FROM " . $this->productTable . " as p
			INNER JOIN " . $this->brandTable . " as b ON b.id = p.brandid
			INNER JOIN " . $this->categoryTable . " as c ON c.categoryid = p.categoryid 
			INNER JOIN " . $this->supplierTable . " as s ON s.supplier_id = p.supplier ";

		// Prepare an array to hold query conditions and parameters
		$queryParams = [];
		$queryConditions = [];

		// Handle search input securely
		if (isset($_POST["search"]["value"]) && !empty($_POST["search"]["value"])) {
			$searchValue = '%' . $_POST["search"]["value"] . '%';
			$queryConditions[] = '(b.bname LIKE ? OR c.name LIKE ? OR p.pname LIKE ? OR p.quantity LIKE ? OR s.supplier_name LIKE ? OR p.pid LIKE ?)';
			for ($i = 0; $i < 6; $i++) {
				$queryParams[] = $searchValue; // Reuse the search value for all searchable fields
			}
		}

		// Append WHERE clause if there are conditions
		if (count($queryConditions) > 0) {
			$sqlQuery .= ' WHERE ' . implode(' AND ', $queryConditions);
		}

		// Handle ordering
		if (isset($_POST['order'])) {
			$columnIndex = intval($_POST['order']['0']['column']);
			$columnOrder = $_POST['order']['0']['dir'] === 'asc' ? 'ASC' : 'DESC';
			$sqlQuery .= ' ORDER BY ' . $this->getColumnByIndex($columnIndex) . ' ' . $columnOrder;
		} else {
			$sqlQuery .= ' ORDER BY p.pid DESC';
		}

		// Handle pagination
		if ($_POST['length'] != -1) {
			$sqlQuery .= ' LIMIT ?, ?';
			$queryParams[] = intval($_POST['start']);
			$queryParams[] = intval($_POST['length']);
		}

		// Prepare and bind parameters using prepared statements
		if ($stmt = mysqli_prepare($this->dbConnect, $sqlQuery)) {
			// If we have search parameters, bind them accordingly
			if (count($queryParams) > 0) {
				$types = str_repeat('s', count($queryParams) - 2) . 'ii';  // 's' for strings, 'ii' for integers (pagination)
				mysqli_stmt_bind_param($stmt, $types, ...$queryParams);
			}

			// Execute the statement
			mysqli_stmt_execute($stmt);
			$result = mysqli_stmt_get_result($stmt);
			$numRows = mysqli_num_rows($result);

			// Process the result into productData array
			$productData = [];
			while ($product = mysqli_fetch_assoc($result)) {
				$status = $product['status'] == 'active' ?
					'<span class="label label-success">Active</span>' :
					'<span class="label label-danger">Inactive</span>';

				$productRow = [];
				$productRow[] = $product['pid'];
				$productRow[] = $product['name'];
				$productRow[] = $product['bname'];
				$productRow[] = $product['pname'];
				$productRow[] = $product['model'];
				$productRow[] = $product["quantity"];
				$productRow[] = $product['supplier_name'];
				$productRow[] = $status;
				$productRow[] = '<div class="btn-group btn-group-sm">
					<button type="button" name="view" id="' . $product["pid"] . '" class="btn btn-light bg-gradient border text-dark btn-sm rounded-0 view" title="View"><i class="fa fa-eye"></i></button>
					<button type="button" name="update" id="' . $product["pid"] . '" class="btn btn-primary btn-sm rounded-0 update" title="Update"><i class="fa fa-edit"></i></button>
					<button type="button" name="delete" id="' . $product["pid"] . '" class="btn btn-danger btn-sm rounded-0 delete" data-status="' . $product["status"] . '" title="Delete"><i class="fa fa-trash"></i></button>
				</div>';

				$productData[] = $productRow;
			}

			// Prepare output data
			$outputData = [
				"draw" => intval($_POST["draw"]),
				"recordsTotal" => $numRows,
				"recordsFiltered" => $numRows,
				"data" => $productData
			];

			// Return JSON encoded data
			echo json_encode($outputData);

			// Close the statement
			mysqli_stmt_close($stmt);
		} else {
			// Handle error case
			echo json_encode(['error' => 'An unexpected error occurred. Please try again later.']);
		}
	}


	public function getCategoryBrand($categoryid)
	{

		$sqlQuery = "SELECT * FROM " . $this->brandTable . " 
			WHERE status = 'active' AND categoryid = ? ORDER BY bname ASC";
		if ($stmt = mysqli_prepare($this->dbConnect, $sqlQuery)) {
			mysqli_stmt_bind_param($stmt, 'i', $categoryid);
			mysqli_stmt_execute($stmt);
			$result = mysqli_stmt_get_result($stmt);
			$dropdownHTML = '';
			while ($brand = mysqli_fetch_assoc($result)) {
				$dropdownHTML .= '<option value="' . $brand["id"] . '">' . $brand["bname"] . '</option>';
			}
			mysqli_stmt_close($stmt);
			return $dropdownHTML;
		} else {
			return 'An unexpected error occurred. Please try again later.';
		}
	}


	public function supplierDropdownList()
	{
		$sqlQuery = "SELECT * FROM " . $this->supplierTable . " 
			WHERE status = 'active' 
			ORDER BY supplier_name ASC";

		if ($stmt = mysqli_prepare($this->dbConnect, $sqlQuery)) {
			mysqli_stmt_execute($stmt);
			$result = mysqli_stmt_get_result($stmt);

			$dropdownHTML = '';
			while ($supplier = mysqli_fetch_assoc($result)) {
				$dropdownHTML .= '<option value="' . $supplier["supplier_id"] . '">' . $supplier["supplier_name"] . '</option>';
			}

			mysqli_stmt_close($stmt);
			return $dropdownHTML;
		} else {
			return 'Database error';
		}
	}

	public function addProduct()
	{
		$sqlInsert = "
			INSERT INTO " . $this->productTable . " (categoryid, brandid, pname, model, description, quantity, unit, base_price, tax, minimum_order, supplier) 
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

		if ($stmt = mysqli_prepare($this->dbConnect, $sqlInsert)) {
			// Assuming all input values are strings except for categoryid, brandid, quantity, base_price, tax, and minimum_order which are integers or floats
			mysqli_stmt_bind_param(
				$stmt,
				'iissssssdis',
				$_POST["categoryid"],
				$_POST['brandid'],
				$_POST['pname'],
				$_POST['pmodel'],
				$_POST['description'],
				$_POST['quantity'],
				$_POST['unit'],
				$_POST['base_price'],
				$_POST['tax'],
				$minimum_order = 1,  // Hardcoded value for minimum_order
				$_POST['supplierid']
			);

			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
			echo 'New Product Added';
		} else {
			echo 'Database error';
		}
	}

	public function getProductDetails()
	{
		$sqlQuery = "SELECT * FROM " . $this->productTable . " WHERE pid = ?";

		if ($stmt = mysqli_prepare($this->dbConnect, $sqlQuery)) {
			mysqli_stmt_bind_param($stmt, 'i', $_POST["pid"]);  // Assuming pid is an integer
			mysqli_stmt_execute($stmt);
			$result = mysqli_stmt_get_result($stmt);

			$output = array();
			while ($product = mysqli_fetch_assoc($result)) {
				$output['pid'] = $product['pid'];
				$output['categoryid'] = $product['categoryid'];
				$output['brandid'] = $product['brandid'];
				$output["brand_select_box"] = $this->getCategoryBrand($product['categoryid']);
				$output['pname'] = $product['pname'];
				$output['model'] = $product['model'];
				$output['description'] = $product['description'];
				$output['quantity'] = $product['quantity'];
				$output['unit'] = $product['unit'];
				$output['base_price'] = $product['base_price'];
				$output['tax'] = $product['tax'];
				$output['supplier'] = $product['supplier'];
			}

			mysqli_stmt_close($stmt);
			echo json_encode($output);
		} else {
			echo 'Database error';
		}
	}

	public function updateProduct()
	{
		if ($_POST['pid']) {
			$sqlUpdate = "UPDATE " . $this->productTable . " 
				SET categoryid = ?, brandid = ?, pname = ?, model = ?, description = ?, quantity = ?, unit = ?, base_price = ?, tax = ?, supplier = ? 
				WHERE pid = ?";

			if ($stmt = mysqli_prepare($this->dbConnect, $sqlUpdate)) {
				mysqli_stmt_bind_param(
					$stmt,
					'iissssssdi',
					$_POST['categoryid'],
					$_POST['brandid'],
					$_POST['pname'],
					$_POST['pmodel'],
					$_POST['description'],
					$_POST['quantity'],
					$_POST['unit'],
					$_POST['base_price'],
					$_POST['tax'],
					$_POST['supplierid'],
					$_POST['pid']  // Assuming pid is an integer
				);

				mysqli_stmt_execute($stmt);
				mysqli_stmt_close($stmt);
				echo 'Product Updated';
			} else {
				echo 'Database error';
			}
		}
	}

	public function deleteProduct()
	{
		$sqlQuery = "DELETE FROM " . $this->productTable . " WHERE pid = ?";

		if ($stmt = mysqli_prepare($this->dbConnect, $sqlQuery)) {
			mysqli_stmt_bind_param($stmt, 'i', $_POST["pid"]);  // Assuming pid is an integer
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
			echo 'Product Deleted';
		} else {
			echo 'Database error';
		}
	}

	public function viewProductDetails()
	{
		$sqlQuery = "SELECT * FROM " . $this->productTable . " as p
			INNER JOIN " . $this->brandTable . " as b ON b.id = p.brandid
			INNER JOIN " . $this->categoryTable . " as c ON c.categoryid = p.categoryid 
			INNER JOIN " . $this->supplierTable . " as s ON s.supplier_id = p.supplier 
			WHERE p.pid = ?";

		if ($stmt = mysqli_prepare($this->dbConnect, $sqlQuery)) {
			mysqli_stmt_bind_param($stmt, 'i', $_POST["pid"]);  // Assuming pid is an integer
			mysqli_stmt_execute($stmt);
			$result = mysqli_stmt_get_result($stmt);

			$productDetails = '<div class="table-responsive">
				<table class="table table-bordered">';

			while ($product = mysqli_fetch_assoc($result)) {
				$status = ($product['status'] == 'active')
					? '<span class="label label-success">Active</span>'
					: '<span class="label label-danger">Inactive</span>';

				$productDetails .= '
				<tr>
					<td>Product Name</td>
					<td>' . $product["pname"] . '</td>
				</tr>
				<tr>
					<td>Product Model</td>
					<td>' . $product["model"] . '</td>
				</tr>
				<tr>
					<td>Product Description</td>
					<td>' . $product["description"] . '</td>
				</tr>
				<tr>
					<td>Category</td>
					<td>' . $product["name"] . '</td>
				</tr>
				<tr>
					<td>Brand</td>
					<td>' . $product["bname"] . '</td>
				</tr>
				<tr>
					<td>Available Quantity</td>
					<td>' . $product["quantity"] . ' ' . $product["unit"] . '</td>
				</tr>
				<tr>
					<td>Base Price</td>
					<td>' . $product["base_price"] . '</td>
				</tr>
				<tr>
					<td>Tax (%)</td>
					<td>' . $product["tax"] . '</td>
				</tr>
				<tr>
					<td>Entered By</td>
					<td>' . $product["supplier_name"] . '</td>
				</tr>
				<tr>
					<td>Status</td>
					<td>' . $status . '</td>
				</tr>';
			}

			$productDetails .= '
				</table>
			</div>';

			mysqli_stmt_close($stmt);
			echo $productDetails;
		} else {
			echo 'Database error';
		}
	}

	// supplier 
	public function getSupplierList()
	{
		$searchValue = !empty($_POST["search"]["value"]) ? $_POST["search"]["value"] : '';
		$orderColumnIndex = isset($_POST['order']['0']['column']) ? (int)$_POST['order']['0']['column'] : 0;
		$orderDirection = isset($_POST['order']['0']['dir']) ? $_POST['order']['0']['dir'] : 'DESC';
		$start = isset($_POST['start']) ? (int)$_POST['start'] : 0;
		$length = isset($_POST['length']) ? (int)$_POST['length'] : 10;

		// Define allowed columns for sorting
		$allowedColumns = ['supplier_id', 'supplier_name', 'mobile', 'address', 'status'];
		$orderByColumn = isset($allowedColumns[$orderColumnIndex]) ? $allowedColumns[$orderColumnIndex] : 'supplier_id';

		// Validate order direction
		$orderDirection = strtoupper($orderDirection) === 'ASC' ? 'ASC' : 'DESC';

		// Prepare SQL query with placeholders
		$sqlQuery = "SELECT * FROM " . $this->supplierTable . " 
					 WHERE supplier_name LIKE ? 
					 OR address LIKE ? 
					 ORDER BY $orderByColumn $orderDirection 
					 LIMIT ?, ?";

		if ($stmt = mysqli_prepare($this->dbConnect, $sqlQuery)) {
			// Bind parameters
			$searchTerm = "%$searchValue%";
			mysqli_stmt_bind_param($stmt, 'ssii', $searchTerm, $searchTerm, $start, $length);

			// Execute the statement
			mysqli_stmt_execute($stmt);

			// Get the result
			$result = mysqli_stmt_get_result($stmt);

			$supplierData = array();
			while ($supplier = mysqli_fetch_assoc($result)) {
				$status = $supplier['status'] == 'active'
					? '<span class="label label-success">Active</span>'
					: '<span class="label label-danger">Inactive</span>';

				$supplierRows = array();
				$supplierRows[] = $supplier['supplier_id'];
				$supplierRows[] = $supplier['supplier_name'];
				$supplierRows[] = $supplier['mobile'];
				$supplierRows[] = $supplier['address'];
				$supplierRows[] = $status;
				$supplierRows[] = '<div class="btn-group btn-group-sm">
					<button type="button" name="update" id="' . $supplier["supplier_id"] . '" class="btn btn-primary btn-sm rounded-0 update" title="Update">
						<i class="fa fa-edit"></i>
					</button>
					<button type="button" name="delete" id="' . $supplier["supplier_id"] . '" class="btn btn-danger btn-sm rounded-0 delete" title="Delete">
						<i class="fa fa-trash"></i>
					</button>
				</div>';

				$supplierData[] = $supplierRows;
			}

			// Prepare total count query
			$totalQuery = "SELECT COUNT(*) as total FROM " . $this->supplierTable;

			if ($totalStmt = mysqli_prepare($this->dbConnect, $totalQuery)) {
				// Execute total count query
				mysqli_stmt_execute($totalStmt);
				$totalResult = mysqli_stmt_get_result($totalStmt);
				$totalRow = mysqli_fetch_assoc($totalResult);
				$totalRecords = $totalRow['total'];

				// Close the total count statement
				mysqli_stmt_close($totalStmt);
			} else {
				$totalRecords = 0;
			}

			// Prepare output
			$output = array(
				"draw" => intval($_POST["draw"]),
				"recordsTotal" => $totalRecords,
				"recordsFiltered" => mysqli_num_rows($result),
				"data" => $supplierData
			);

			// Close the main statement
			mysqli_stmt_close($stmt);

			// Output the result
			echo json_encode($output);
		} else {
			echo 'Database error';
		}
	}

	public function addSupplier()
	{

		$sqlInsert = "INSERT INTO " . $this->supplierTable . " (supplier_name, mobile, address) VALUES (?, ?, ?)";
		if ($stmt = mysqli_prepare($this->dbConnect, $sqlInsert)) {
			mysqli_stmt_bind_param($stmt, 'sss', $_POST['supplier_name'], $_POST['mobile'], $_POST['address']);
			if (mysqli_stmt_execute($stmt)) {
				echo 'New Supplier Added';
			} else {
				// Show a generic message to the user
				echo 'An unexpected error occurred. Please try again later.';
			}
			mysqli_stmt_close($stmt);
		} else {
			// Show a generic message to the user
			echo 'An unexpected error occurred. Please try again later.';
		}
	}
	public function getSupplier()
	{
		$sqlQuery = "SELECT * FROM " . $this->supplierTable . " WHERE supplier_id = ?";

		if ($stmt = mysqli_prepare($this->dbConnect, $sqlQuery)) {
			mysqli_stmt_bind_param($stmt, 'i', $_POST["supplier_id"]);
			mysqli_stmt_execute($stmt);
			$result = mysqli_stmt_get_result($stmt);
			$row = mysqli_fetch_array($result, MYSQLI_ASSOC);
			echo json_encode($row);
			mysqli_stmt_close($stmt);
		} else {
			// Show a generic message to the user
			echo 'An unexpected error occurred. Please try again later.';
		}
	}

	public function updateSupplier()
	{
		if ($_POST['supplier_id']) {
			$sqlUpdate = "UPDATE " . $this->supplierTable . " 
				SET supplier_name = ?, mobile = ?, address = ? 
				WHERE supplier_id = ?";

			if ($stmt = mysqli_prepare($this->dbConnect, $sqlUpdate)) {
				mysqli_stmt_bind_param($stmt, 'sssi', $_POST['supplier_name'], $_POST['mobile'], $_POST['address'], $_POST['supplier_id']);
				mysqli_stmt_execute($stmt);
				echo 'Supplier Edited';
				mysqli_stmt_close($stmt);
			} else {
				// Show a generic message to the user
				echo 'An unexpected error occurred. Please try again later.';
			}
		}
	}

	public function deleteSupplier()
	{
		$sqlQuery = "DELETE FROM " . $this->supplierTable . " WHERE supplier_id = ?";

		if ($stmt = mysqli_prepare($this->dbConnect, $sqlQuery)) {
			mysqli_stmt_bind_param($stmt, 'i', $_POST['supplier_id']);
			mysqli_stmt_execute($stmt);
			echo 'Supplier Deleted';
			mysqli_stmt_close($stmt);
		} else {
			// Show a generic message to the user
			echo 'An unexpected error occurred. Please try again later.';
		}
	}

	// purchase
	public function listPurchase()
	{
		$searchValue = !empty($_POST["search"]["value"]) ? $_POST["search"]["value"] : '';
		$orderColumnIndex = isset($_POST['order']['0']['column']) ? (int)$_POST['order']['0']['column'] : 0;
		$orderDirection = isset($_POST['order']['0']['dir']) ? $_POST['order']['0']['dir'] : 'DESC';
		$start = isset($_POST['start']) ? (int)$_POST['start'] : 0;
		$length = isset($_POST['length']) ? (int)$_POST['length'] : 10;

		// Define allowed columns for sorting
		$allowedColumns = ['purchase_id', 'pname', 'quantity', 'supplier_name'];
		$orderByColumn = isset($allowedColumns[$orderColumnIndex]) ? $allowedColumns[$orderColumnIndex] : 'purchase_id';

		// Validate order direction
		$orderDirection = strtoupper($orderDirection) === 'ASC' ? 'ASC' : 'DESC';

		// Prepare SQL query with placeholders
		$sqlQuery = "SELECT ph.*, p.pname, s.supplier_name 
					 FROM " . $this->purchaseTable . " AS ph
					 INNER JOIN " . $this->productTable . " AS p ON p.pid = ph.product_id 
					 INNER JOIN " . $this->supplierTable . " AS s ON s.supplier_id = ph.supplier_id
					 WHERE ph.purchase_id LIKE ? 
					 OR p.pname LIKE ? 
					 OR s.supplier_name LIKE ? 
					 ORDER BY $orderByColumn $orderDirection 
					 LIMIT ?, ?";

		if ($stmt = mysqli_prepare($this->dbConnect, $sqlQuery)) {
			// Bind parameters
			$searchTerm = "%$searchValue%";
			mysqli_stmt_bind_param($stmt, 'sssii', $searchTerm, $searchTerm, $searchTerm, $start, $length);

			// Execute the statement
			mysqli_stmt_execute($stmt);

			// Get the result
			$result = mysqli_stmt_get_result($stmt);
			$numRows = mysqli_num_rows($result);
			$purchaseData = array();

			while ($purchase = mysqli_fetch_assoc($result)) {
				$productRow = array();
				$productRow[] = $purchase['purchase_id'];
				$productRow[] = $purchase['pname'];
				$productRow[] = $purchase['quantity'];
				$productRow[] = $purchase['supplier_name'];
				$productRow[] = '<div class="btn-group btn-group-sm">
									<button type="button" name="update" id="' . $purchase["purchase_id"] . '" class="btn btn-primary btn-sm rounded-0 update" title="Update">
										<i class="fa fa-edit"></i>
									</button>
									<button type="button" name="delete" id="' . $purchase["purchase_id"] . '" class="btn btn-danger btn-sm rounded-0 delete" title="Delete">
										<i class="fa fa-trash"></i>
									</button>
								</div>';
				$purchaseData[] = $productRow;
			}

			// For total records
			$totalQuery = "SELECT COUNT(*) FROM " . $this->purchaseTable;
			if ($totalResult = mysqli_query($this->dbConnect, $totalQuery)) {
				$totalRecords = mysqli_fetch_array($totalResult)[0];
			} else {
				$totalRecords = 0;
			}

			// Prepare output
			$output = array(
				"draw" => intval($_POST["draw"]),
				"recordsTotal" => $totalRecords,
				"recordsFiltered" => $numRows,
				"data" => $purchaseData
			);

			// Output the result
			echo json_encode($output);

			// Close the statement
			mysqli_stmt_close($stmt);
		} else {
			// Show a generic message to the user
			echo 'An unexpected error occurred. Please try again later.';
		}
	}



	public function productDropdownList()
	{
		// Prepare the SQL query
		$sqlQuery = "SELECT * FROM " . $this->productTable . " ORDER BY pname ASC";

		// Use a prepared statement
		if ($stmt = mysqli_prepare($this->dbConnect, $sqlQuery)) {
			// Execute the statement
			mysqli_stmt_execute($stmt);

			// Get the result
			$result = mysqli_stmt_get_result($stmt);

			$dropdownHTML = '';
			while ($product = mysqli_fetch_assoc($result)) {
				// Safely output the HTML
				$dropdownHTML .= '<option value="' . htmlspecialchars($product["pid"], ENT_QUOTES, 'UTF-8') . '">' .
					htmlspecialchars($product["pname"], ENT_QUOTES, 'UTF-8') . '</option>';
			}

			// Free result set
			mysqli_free_result($result);

			// Close the statement
			mysqli_stmt_close($stmt);

			return $dropdownHTML;
		} else {
			// Show a generic message to the user
			die('An unexpected error occurred. Please try again later.');
		}
	}


	public function addPurchase()
	{
		if (isset($_POST['product'], $_POST['quantity'], $_POST['supplierid'])) {
			$productId = $_POST['product'];
			$quantity = $_POST['quantity'];
			$supplierId = $_POST['supplierid'];

			$sqlInsert = "INSERT INTO " . $this->purchaseTable . " (product_id, quantity, supplier_id) VALUES (?, ?, ?)";
			if ($stmt = mysqli_prepare($this->dbConnect, $sqlInsert)) {
				mysqli_stmt_bind_param($stmt, "iii", $productId, $quantity, $supplierId);
				mysqli_stmt_execute($stmt);
				mysqli_stmt_close($stmt);
				echo 'New Purchase Added';
			} else {
				// Show a generic message to the user
				echo 'An unexpected error occurred. Please try again later.';
			}
		}
	}

	public function getPurchaseDetails()
	{
		if (isset($_POST["purchase_id"])) {
			$purchaseId = $_POST["purchase_id"];
			$sqlQuery = "SELECT * FROM " . $this->purchaseTable . " WHERE purchase_id = ?";
			if ($stmt = mysqli_prepare($this->dbConnect, $sqlQuery)) {
				mysqli_stmt_bind_param($stmt, "i", $purchaseId);
				mysqli_stmt_execute($stmt);
				$result = mysqli_stmt_get_result($stmt);
				$row = mysqli_fetch_array($result, MYSQLI_ASSOC);
				mysqli_stmt_close($stmt);
				echo json_encode($row);
			} else {
				// Show a generic message to the user
				echo 'An unexpected error occurred. Please try again later.';
			}
		}
	}

	public function updatePurchase()
	{
		if (isset($_POST['purchase_id'], $_POST['product'], $_POST['quantity'], $_POST['supplierid'])) {
			$purchaseId = $_POST['purchase_id'];
			$productId = $_POST['product'];
			$quantity = $_POST['quantity'];
			$supplierId = $_POST['supplierid'];

			$sqlUpdate = "UPDATE " . $this->purchaseTable . " SET product_id = ?, quantity = ?, supplier_id = ? WHERE purchase_id = ?";
			if ($stmt = mysqli_prepare($this->dbConnect, $sqlUpdate)) {
				mysqli_stmt_bind_param($stmt, "iiii", $productId, $quantity, $supplierId, $purchaseId);
				mysqli_stmt_execute($stmt);
				mysqli_stmt_close($stmt);
				echo 'Purchase Edited';
			} else {
				// Show a generic message to the user
				echo 'An unexpected error occurred. Please try again later.';
			}
		}
	}

	public function deletePurchase()
	{
		if (isset($_POST['purchase_id'])) {
			$purchaseId = $_POST['purchase_id'];
			$sqlQuery = "DELETE FROM " . $this->purchaseTable . " WHERE purchase_id = ?";
			if ($stmt = mysqli_prepare($this->dbConnect, $sqlQuery)) {
				mysqli_stmt_bind_param($stmt, "i", $purchaseId);
				mysqli_stmt_execute($stmt);
				mysqli_stmt_close($stmt);
			} else {
				// Show a generic message to the user
				echo 'An unexpected error occurred. Please try again later.';
			}
		}
	}

	// order
	public function listOrders()
	{
		$searchValue = !empty($_POST["search"]["value"]) ? $_POST["search"]["value"] : '';
		$orderColumnIndex = isset($_POST['order']['0']['column']) ? (int)$_POST['order']['0']['column'] : 0;
		$orderDirection = isset($_POST['order']['0']['dir']) ? $_POST['order']['0']['dir'] : 'DESC';
		$start = isset($_POST['start']) ? (int)$_POST['start'] : 0;
		$length = isset($_POST['length']) ? (int)$_POST['length'] : 10;

		// Define allowed columns for sorting
		$allowedColumns = ['order_id', 'pname', 'total_shipped', 'name'];
		$orderByColumn = isset($allowedColumns[$orderColumnIndex]) ? $allowedColumns[$orderColumnIndex] : 'order_id';

		// Validate order direction
		$orderDirection = strtoupper($orderDirection) === 'ASC' ? 'ASC' : 'DESC';

		// Prepare SQL query with placeholders
		$sqlQuery = "SELECT o.order_id, p.pname, o.total_shipped, c.name 
					 FROM " . $this->orderTable . " AS o
					 INNER JOIN " . $this->customerTable . " AS c ON c.id = o.customer_id
					 INNER JOIN " . $this->productTable . " AS p ON p.pid = o.product_id 
					 WHERE o.order_id LIKE ? 
					 OR p.pname LIKE ? 
					 OR c.name LIKE ? 
					 ORDER BY $orderByColumn $orderDirection 
					 LIMIT ?, ?";

		if ($stmt = mysqli_prepare($this->dbConnect, $sqlQuery)) {
			// Bind parameters
			$searchTerm = "%$searchValue%";
			mysqli_stmt_bind_param($stmt, 'sssii', $searchTerm, $searchTerm, $searchTerm, $start, $length);

			// Execute the statement
			mysqli_stmt_execute($stmt);

			// Get the result
			$result = mysqli_stmt_get_result($stmt);

			$orderData = array();
			while ($order = mysqli_fetch_assoc($result)) {
				$orderRow = array();
				$orderRow[] = $order['order_id'];
				$orderRow[] = $order['pname'];
				$orderRow[] = $order['total_shipped'];
				$orderRow[] = $order['name'];
				$orderRow[] = '<div class="btn-group btn-group-sm">
					<button type="button" name="update" id="' . $order["order_id"] . '" class="btn btn-primary btn-sm rounded-0 update" title="Update">
						<i class="fa fa-edit"></i>
					</button>
					<button type="button" name="delete" id="' . $order["order_id"] . '" class="btn btn-danger btn-sm rounded-0 delete" title="Delete">
						<i class="fa fa-trash"></i>
					</button>
				</div>';
				$orderData[] = $orderRow;
			}

			// Prepare total count query
			$totalQuery = "SELECT COUNT(*) as total FROM " . $this->orderTable;
			if ($totalStmt = mysqli_prepare($this->dbConnect, $totalQuery)) {
				mysqli_stmt_execute($totalStmt);
				$totalResult = mysqli_stmt_get_result($totalStmt);
				$totalRow = mysqli_fetch_assoc($totalResult);
				$totalRecords = $totalRow['total'];
				mysqli_stmt_close($totalStmt);
			} else {
				$totalRecords = 0;
			}

			// Prepare output
			$output = array(
				"draw" => intval($_POST["draw"]),
				"recordsTotal" => $totalRecords,
				"recordsFiltered" => mysqli_num_rows($result),
				"data" => $orderData
			);

			// Close the main statement
			mysqli_stmt_close($stmt);

			// Output the result
			echo json_encode($output);
		} else {
			echo 'Database error';
		}
	}


	public function addOrder()
	{
		// Prepare the SQL statement
		$sqlInsert = "
			INSERT INTO " . $this->orderTable . " (product_id, total_shipped, customer_id) 
			VALUES (?, ?, ?)";
		if ($stmt = mysqli_prepare($this->dbConnect, $sqlInsert)) {
			// Bind parameters
			mysqli_stmt_bind_param($stmt, 'iis', $_POST['product'], $_POST['shipped'], $_POST['customer']);
			// Execute the statement
			mysqli_stmt_execute($stmt);
			echo 'New order added';
			mysqli_stmt_close($stmt);
		} else {
			// Show a generic message to the user
			echo 'An unexpected error occurred. Please try again later.';
		}
	}

	public function getOrderDetails()
	{
		$sqlQuery = "
			SELECT * FROM " . $this->orderTable . " 
			WHERE order_id = ?";
		if ($stmt = mysqli_prepare($this->dbConnect, $sqlQuery)) {
			// Bind parameters
			mysqli_stmt_bind_param($stmt, 'i', $_POST["order_id"]);
			// Execute the statement
			mysqli_stmt_execute($stmt);
			// Get the result
			$result = mysqli_stmt_get_result($stmt);
			$row = mysqli_fetch_array($result, MYSQLI_ASSOC);
			echo json_encode($row);
			mysqli_stmt_close($stmt);
		} else {
			// Show a generic message to the user
			echo 'An unexpected error occurred. Please try again later.';
		}
	}

	public function updateOrder()
	{
		if (isset($_POST['order_id'])) {
			$sqlUpdate = "
				UPDATE " . $this->orderTable . " 
				SET product_id = ?, total_shipped = ?, customer_id = ? 
				WHERE order_id = ?";
			if ($stmt = mysqli_prepare($this->dbConnect, $sqlUpdate)) {
				// Bind parameters
				mysqli_stmt_bind_param($stmt, 'iisi', $_POST['product'], $_POST['shipped'], $_POST['customer'], $_POST['order_id']);
				// Execute the statement
				mysqli_stmt_execute($stmt);
				echo 'Order Edited';
				mysqli_stmt_close($stmt);
			} else {
				// Show a generic message to the user
				echo 'An unexpected error occurred. Please try again later.';
			}
		}
	}

	public function deleteOrder()
	{
		$sqlQuery = "
			DELETE FROM " . $this->orderTable . " 
			WHERE order_id = ?";
		if ($stmt = mysqli_prepare($this->dbConnect, $sqlQuery)) {
			// Bind parameters
			mysqli_stmt_bind_param($stmt, 'i', $_POST['order_id']);
			// Execute the statement
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
		} else {
			// Show a generic message to the user
			echo 'An unexpected error occurred. Please try again later.';
		}
	}

	public function customerDropdownList()
	{
		$sqlQuery = "SELECT * FROM " . $this->customerTable . " ORDER BY name ASC";
		if ($stmt = mysqli_prepare($this->dbConnect, $sqlQuery)) {
			mysqli_stmt_execute($stmt);
			$result = mysqli_stmt_get_result($stmt);

			$dropdownHTML = '';
			while ($customer = mysqli_fetch_assoc($result)) {
				$dropdownHTML .= '<option value="' . htmlspecialchars($customer["id"], ENT_QUOTES, 'UTF-8') . '">' .
					htmlspecialchars($customer["name"], ENT_QUOTES, 'UTF-8') . '</option>';
			}
			mysqli_free_result($result);
			mysqli_stmt_close($stmt);

			return $dropdownHTML;
		} else {

			die('An unexpected error occurred. Please try again later.');
		}
	}

	public function getInventoryDetails()
	{
		// Base SQL query with placeholders
		$sqlQuery = "
			SELECT p.pid, p.pname, p.model, p.quantity AS product_quantity, 
				   COALESCE(s.quantity, 0) AS recieved_quantity, 
				   COALESCE(r.total_shipped, 0) AS total_shipped
			FROM " . $this->productTable . " AS p
			LEFT JOIN " . $this->purchaseTable . " AS s ON s.product_id = p.pid
			LEFT JOIN " . $this->orderTable . " AS r ON r.product_id = p.pid ";

		// Add order clause if specified
		if (isset($_POST['order'])) {
			$columnIndex = intval($_POST['order']['0']['column']);
			$direction = $_POST['order']['0']['dir'] === 'asc' ? 'ASC' : 'DESC';
			$sqlQuery .= "ORDER BY $columnIndex $direction ";
		} else {
			$sqlQuery .= "ORDER BY p.pid DESC ";
		}

		// Add limit clause if specified
		if (isset($_POST['length']) && $_POST['length'] != -1) {
			$start = intval($_POST['start']);
			$length = intval($_POST['length']);
			$sqlQuery .= "LIMIT ?, ?"; // Placeholder for limit values
		}

		// Prepare the SQL statement
		if ($stmt = mysqli_prepare($this->dbConnect, $sqlQuery)) {
			// Bind parameters for limit clause if applicable
			if (isset($start) && isset($length)) {
				mysqli_stmt_bind_param($stmt, 'ii', $start, $length);
			}

			// Execute the statement
			mysqli_stmt_execute($stmt);

			// Get the result
			$result = mysqli_stmt_get_result($stmt);
			$numRows = mysqli_num_rows($result);
			$inventoryData = array();
			$i = 1;

			// Fetch and process the results
			while ($inventory = mysqli_fetch_assoc($result)) {
				$inventoryInHand = ($inventory['product_quantity'] + $inventory['recieved_quantity']) - $inventory['total_shipped'];

				$inventoryRow = array();
				$inventoryRow[] = $i++;
				$inventoryRow[] = "<div class='lh-1'><div>{$inventory['pname']}</div><div class='fw-bolder text-muted'><small>{$inventory['model']}</small></div></div>";
				$inventoryRow[] = $inventory['product_quantity'];
				$inventoryRow[] = $inventory['recieved_quantity'];
				$inventoryRow[] = $inventory['total_shipped'];
				$inventoryRow[] = $inventoryInHand;
				$inventoryData[] = $inventoryRow;
			}

			// Close the statement
			mysqli_stmt_close($stmt);

			// Output the data as JSON
			$output = array(
				"draw" => intval($_POST["draw"]),
				"recordsTotal" => $numRows,
				"recordsFiltered" => $numRows,
				"data" => $inventoryData
			);
			echo json_encode($output);
		} else {
			// Show a generic message to the user
			echo 'An unexpected error occurred. Please try again later.';
		}
	}
}
