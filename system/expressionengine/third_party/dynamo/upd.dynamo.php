<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Dynamo_upd
{
	public $version = '1.0.2';
	
	/**
	 * Dynamo_upd
	 * 
	 * @access	public
	 * @return	void
	 */
	public function __construct()
	{
		$this->EE = get_instance();
	}
	
	/**
	 * install
	 * 
	 * @access	public
	 * @return	void
	 */
	public function install()
	{
		$this->EE->db->insert(
			'modules',
			array(
				'module_name' => 'Dynamo',
				'module_version' => $this->version, 
				'has_cp_backend' => 'n',
				'has_publish_fields' => 'n'
			)
		);
		
		$this->EE->db->insert(
			'actions',
			array(
				'class' => 'Dynamo',
				'method' => 'form_submit'
			)
		);
		
		$this->EE->load->dbforge();
		
		$fields = array(
			'search_id' => array('type' => 'varchar', 'constraint' => '32'),
			'date' => array('type' => 'int', 'constraint' => '10'),
			'parameters' => array('type' => 'text'),
		);
	
		$this->EE->dbforge->add_field($fields);
		$this->EE->dbforge->add_key('search_id', TRUE);
		$this->EE->dbforge->add_key('date');
	
		$this->EE->dbforge->create_table('dynamo');
		
		return TRUE;
	}
	
	/**
	 * uninstall
	 * 
	 * @access	public
	 * @return	void
	 */
	public function uninstall()
	{
		$query = $this->EE->db->get_where('modules', array('module_name' => 'Dynamo'));
		
		if ($query->row('module_id'))
		{
			$this->EE->db->delete('module_member_groups', array('module_id' => $query->row('module_id')));
		}

		$this->EE->db->delete('modules', array('module_name' => 'Dynamo'));
		$this->EE->db->delete('actions', array('class' => 'Dynamo'));
		
		$this->EE->load->dbforge();
		
		$this->EE->dbforge->drop_table('dynamo');

		return TRUE;
	}
	
	/**
	 * update
	 * 
	 * @access	public
	 * @param	mixed $current = ''
	 * @return	void
	 */
	public function update($current = '')
	{
		if ($current == $this->version)
		{
			return FALSE;
		}
		
		return TRUE;
	}
}

/* End of file upd.dynamo.php */
/* Location: ./system/expressionengine/third_party/dynamo/upd.dynamo.php */