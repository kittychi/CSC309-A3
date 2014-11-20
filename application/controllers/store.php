<?php

class Store extends CI_Controller {
     
     
    function __construct() {
		// Call the Controller constructor
    	parent::__construct();
    	
    	
    	$config['upload_path'] = './images/product/';
    	$config['allowed_types'] = 'gif|jpg|png';
/*	    $config['max_size'] = '100';
    	$config['max_width'] = '1024';
    	$config['max_height'] = '768';
*/
    		    	
    	$this->load->library('upload', $config);
    }

    function index() {
		$this->load->model('product_model');
		$products = $this->product_model->getAll();
		$data['products']=$products;

		$data['loggedin'] = $this->session->userdata('logged_in');
		$data['username'] = $this->session->userdata('username');
		$data['isadmin'] = $this->session->userdata('isadmin');

		// $data['loggedin'] = True;
		// $data['username'] = 'admin'; 

		$this->load->view('common/scripts.html');
		$this->load->view('common/header.php', $data);
		$this->load->view('product/storeFront.php',$data);
    }
    
    function viewProducts() { 
    	$this->load->model('product_model');
		$products = $this->product_model->getAll();
		$data['products']=$products;
		$this->load->view('product/list.php',$data);
    }

    function newForm() {
	    	$this->load->view('product/newForm.php');
    }
    
	function create() {
		$this->load->library('form_validation');
		$this->form_validation->set_rules('name','Name','required|is_unique[products.name]');
		$this->form_validation->set_rules('description','Description','required');
		$this->form_validation->set_rules('price','Price','required');
		
		$fileUploadSuccess = $this->upload->do_upload();
		
		if ($this->form_validation->run() == true && $fileUploadSuccess) {
			$this->load->model('product_model');

			$product = new Product();
			$product->name = $this->input->get_post('name');
			$product->description = $this->input->get_post('description');
			$product->price = $this->input->get_post('price');
			
			$data = $this->upload->data();
			$product->photo_url = $data['file_name'];
			
			$this->product_model->insert($product);

			//Then we redirect to the index page again
			redirect('store/index', 'refresh');
		}
		else {
			if ( !$fileUploadSuccess) {
				$data['fileerror'] = $this->upload->display_errors();
				$this->load->view('product/newForm.php',$data);
				return;
			}
			
			$this->load->view('product/newForm.php');
		}	
	}
	
	function read($id) {
		$this->load->model('product_model');
		$product = $this->product_model->get($id);
		$data['product']=$product;
		$this->load->view('product/read.php',$data);
	}
	
	function editForm($id) {
		$this->load->model('product_model');
		$product = $this->product_model->get($id);
		$data['product']=$product;
		$this->load->view('product/editForm.php',$data);
	}
	
	function update($id) {
		$this->load->library('form_validation');
		$this->form_validation->set_rules('name','Name','required');
		$this->form_validation->set_rules('description','Description','required');
		$this->form_validation->set_rules('price','Price','required');
		
		if ($this->form_validation->run() == true) {
			$product = new Product();
			$product->id = $id;
			$product->name = $this->input->get_post('name');
			$product->description = $this->input->get_post('description');
			$product->price = $this->input->get_post('price');
			
			$this->load->model('product_model');
			$this->product_model->update($product);
			//Then we redirect to the index page again
			redirect('store/index', 'refresh');
		}
		else {
			$product = new Product();
			$product->id = $id;
			$product->name = set_value('name');
			$product->description = set_value('description');
			$product->price = set_value('price');
			$data['product']=$product;
			$this->load->view('product/editForm.php',$data);
		}
	}
    	
	function delete($id) {
		$this->load->model('product_model');
		
		if (isset($id)) 
			$this->product_model->delete($id);
		
		//Then we redirect to the index page again
		redirect('store/index', 'refresh');
	}
      
   	function viewCart(){
   		$this->load->view('cart/viewCart.php');
   	}
    
    function addCart($id){
    	$this->load->model('product_model');
		$product = $this->product_model->get($id);

    	session_start();
		
		if (!isset($_SESSION['Cart'])) {
			$_SESSION['Cart'] = array();
		}

		array_push($_SESSION['Cart'], $product);
		viewCart();
    }
    
}

