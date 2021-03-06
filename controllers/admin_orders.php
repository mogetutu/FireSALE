<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Admin_orders extends Admin_Controller
{

	public $perpage = 30;
	public $tabs    = array();
	public $section = 'orders';

	public function __construct()
	{

		parent::__construct();
		
		// Load the models
		$this->load->model('orders_m');
		$this->load->model('products_m');

		// Get the stream
		$this->stream = $this->streams->streams->get_stream('firesale_orders', 'firesale_orders');

		// Add metadata
		$this->template->append_css('module::orders.css')
					   ->append_js('module::orders.js');

	}

	public function index($type = NULL, $query = NULL, $start = 0)
	{

		// Set query paramaters
		$params	 = array(
					'stream' 	=> 'firesale_orders',
					'namespace'	=> 'firesale_orders',
					'limit'		=> $this->perpage,
					'offset'	=> $start,
					'order_by'	=> 'id',
					'sort'		=> 'desc'
				   );
		
		// Get by category if set
		if( $type != NULL AND $query != NULL )
		{
			$params['where'] = $type . '=' . $query;
		}
		
		// Get entries		products
		$orders = $this->streams->entries->get_entries($params);

		// Get product count
		foreach( $orders['entries'] AS $key => $order )
		{
			$orders['entries'][$key]['products'] = $this->orders_m->get_product_count($order['id']);
		}

		// Assign variables
		$this->data->orders     = $orders['entries'];
		$this->data->pagination = $orders['pagination'];
		
		$this->template->title(lang('firesale:title') . ' ' . lang('firesale:sections:orders'))
					   ->build('admin/orders/index', $this->data);
	}

	public function create($id = NULL, $row = NULL)
	{

		// Check for post data
		if( $this->input->post('btnAction') == 'save' )
		{
			
			// Variables
			$input 	= $this->input->post();
			$skip	= array('btnAction');
			$extra 	= array(
						'return' 			=> '/admin/firesale/orders/edit/-id-',
						'success_message'	=> lang('firesale:order_' . ( $id == NULL ? 'add' : 'edit' ) . '_success'),
						'error_message'		=> lang('firesale:prod_' . ( $id == NULL ? 'add' : 'edit' ) . '_error')
					  );
		
		}
		else
		{
			$input = FALSE;
			$skip  = array();
			$extra = array();
		}
	
		// Get the stream fields
		$fields = $this->fields->build_form($this->stream, ( $id == NULL ? 'new' : 'edit' ), ( $id == NULL ? $input : $row ), FALSE, FALSE, $skip, $extra);

		// Assign variables
		if( $row !== NULL ) { $this->data = $row; }
		$this->data->id		= $id;
		$this->data->fields = $this->orders_m->fields_to_tabs($fields, $this->tabs);
		$this->data->tabs	= array_reverse(array_keys($this->data->fields));

		// Get products
		if( $id != NULL )
		{
			$products = $this->orders_m->order_products($id);
			$this->data->products = $products['products'];
		}		

		// Add users as first general field
		$users = $this->orders_m->user_field(( $row != NULL ? $row->created_by : NULL ));
		array_unshift($this->data->fields['general']['details'], $users);
			
		// Build the page
		$this->template->title(lang('firesale:title') . ' ' . sprintf(lang('firesale:orders:title_' . ( $id == NULL ? 'create' : 'edit' )), $id))
					   ->build('admin/orders/create', $this->data);
	}

	public function edit($id)
	{
		
		// Get row
		if( $row = $this->row_m->get_row($id, $this->stream, FALSE) )
		{
			// Load form
			$this->create($id, $row);
		}
		else
		{
			$this->session->set_flashdata('error', lang('firesale:order_not_found'));
			redirect('admin/firesale/orders/create');
		}

	}

	public function status()
	{

		// Variables
		$input = $this->input->post();

		// Check for inputs
		if( isset($input['btnAction']) AND count($input['action_to']) > 0 )
		{

			switch($input['btnAction'])
			{
				case 'paid':	   $status = '2'; break;
				case 'dispatched': $status = '3'; break;
				case 'processing': $status = '4'; break;
				case 'refunded':   $status = '5'; break;
				case 'cancelled':  $status = '6'; break;
				default:		   $status = '1'; break;
			}

			foreach( $input['action_to'] AS $order )
			{
				$this->db->where('id', $order)->update('firesale_orders', array('status' => $status));
			}

		}

		// Redirect
		redirect('/admin/firesale/orders');
	}
	
}
