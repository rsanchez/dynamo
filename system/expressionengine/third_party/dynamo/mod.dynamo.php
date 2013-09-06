<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Dynamo
{
	protected $search;
	protected $channel;
	
	public function __construct()
	{
		$this->EE =& get_instance();
	}
	
	/* TAGS */
	
	public function form()
	{
		$this->EE->load->helper(array('form', 'url'));
		
		$form = array(
			'hidden_fields' => array(
				'ACT' => $this->EE->functions->fetch_action_id('Dynamo', 'form_submit'),
				'RET' => current_url(),
				'return' => $this->EE->TMPL->fetch_param('return', $this->EE->uri->uri_string()),
			),
		);
		
		foreach (array('id', 'class', 'onsubmit', 'name') as $key)
		{
			if ($this->EE->TMPL->fetch_param($key))
			{
				$form[$key] = $this->EE->TMPL->fetch_param($key);
			}
		}

		if ($this->EE->TMPL->fetch_param('secure_action') === 'yes') {
			$form['action'] = str_replace('http://', 'https://', $this->EE->functions->create_url($this->EE->uri->uri_string()));
		}

		if ($this->EE->TMPL->fetch_param('secure_return') === 'yes') {
			$form['hidden_fields']['secure_return'] = true;
		}
		
		$vars = $this->search($this->EE->TMPL->fetch_param('search_id'));
		
		if ($this->EE->TMPL->fetch_param('dynamic_parameters'))
		{
			foreach (explode('|', $this->EE->TMPL->fetch_param('dynamic_parameters')) as $key)
			{
				if ( ! isset($vars[$key]))
				{
					$vars[$key] = '';
				}
			}
		}
		
		//added so you could do array checkboxes with {if {selected:category="10"}}{/if}
		foreach ($this->EE->TMPL->var_single as $var)
		{
			if (preg_match('/^selected:(.+?)\s*==?\s*([\042\047])?(.*)\\2$/', $var, $match))
			{
				$array = (strpos($vars[$match[1]], '|') !== FALSE) ? explode('|', $vars[$match[1]]) : array($vars[$match[1]]);
				
				$vars[$var] = (in_array($match[3], $array)) ? 1 : 0;
			}
		}
		
		$option_fields = array();
		
		foreach (array_keys($this->EE->TMPL->var_pair) as $full_name)
		{
			if (strncmp($full_name, 'options:', 8) !== 0)
			{
				continue;
			}
			
			$option_fields[substr($full_name, 8)] = NULL;
		}
		
		if ($option_fields)
		{
			$query = $this->EE->db->select('field_name, field_type, field_list_items, field_settings, field_id')
						->where_in('field_name', array_keys($option_fields))
						->get('channel_fields');
			
			foreach ($query->result() as $row)
			{
				$options = array();
				
				switch($row->field_type)
				{
					case 'pt_checkboxes':
					case 'pt_radio_buttons':
					case 'pt_dropdown':
					case 'pt_multiselect':
					case 'pt_pill':
					case 'fieldpack_checkboxes':
					case 'fieldpack_radio_buttons':
					case 'fieldpack_dropdown':
					case 'fieldpack_multiselect':
					case 'fieldpack_pill':
						
						$field_settings = @unserialize(base64_decode($row->field_settings));
						
						if (isset($field_settings['options']))
						{
							$options = $field_settings['options'];
						}
						
						break;
					case 'pt_switch':
						
						$field_settings = @unserialize(base64_decode($row->field_settings));
						
						if (is_array($field_settings))
						{
							$options = array(
								$field_settings['off_val'] => $field_settings['off_label'],
								$field_settings['on_val'] => $field_settings['on_label'],
							);
						}
						
						break;
					case 'text':
						
						$channel_query = $this->EE->db->distinct()
										->select('field_id_'.$row->field_id)
										->where('field_id_'.$row->field_id.' !=', '')
										->get('channel_data');
						
						foreach ($channel_query->result() as $row)
						{
							$options[$row->{'field_id_'.$row->field_id}] = $row->{'field_id_'.$row->field_id};
						}
						
						$channel_query->free_result();
						
						break;
					
					default:
					
						if ($row->field_list_items)
						{
							foreach (preg_split('/[\r\n]+/', $row->field_list_items) as $option_value)
							{
								$options[$option_value] = $option_value;
							}
						}
						
						break;
				}
				
				$option_fields[$row->field_name] = $options;
			}
			
			$query->free_result();
			
			foreach ($option_fields as $field_name => $options)
			{
				if (is_null($options))
				{
					$vars['options:'.$field_name] = array(array());
				}
				else
				{
					$vars['options:'.$field_name] = array();
					
					foreach ($options as $option_value => $option_name)
					{
						$vars['options:'.$field_name][] = array(
							'option_value' => $option_value,
							'option_name' => $option_name,
						);
					}
				}
			}
		}
		
		if ( ! isset($vars['keywords']))
		{
			$vars['keywords'] = '';
		}
		
		return $this->EE->functions->form_declaration($form)
			.$this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, array($vars))
			.form_close();
	}
	
	public function entries()
	{
		if (is_null($this->channel))
		{
			require_once APPPATH.'modules/channel/mod.channel'.EXT;
		
			$this->channel = new Channel;
		}
		
		$_POST = array_merge($_POST, $this->search($this->EE->TMPL->fetch_param('search_id')));
		
		if (version_compare(APP_VER, '2.6', '<'))
		{
			$this->EE->TMPL->tagdata = $this->EE->TMPL->assign_relationship_data($this->EE->TMPL->tagdata);

			if (count($this->EE->TMPL->related_markers) > 0)
			{
				foreach ($this->EE->TMPL->related_markers as $marker)
				{
					if ( ! isset($this->EE->TMPL->var_single[$marker]))
					{
						$this->EE->TMPL->var_single[$marker] = $marker;
					}
				}
			}

			if ($this->EE->TMPL->related_id)
			{
				$this->EE->TMPL->var_single[$this->EE->TMPL->related_id] = $this->EE->TMPL->related_id;
				
				$this->EE->TMPL->related_id = '';
			}
		}
		
		if (isset($_POST['entry_ids']))
		{
			$this->EE->TMPL->tagparams['entry_id'] = implode('|', $_POST['entry_ids']);
		}
		
		return $this->channel->entries();
	}
	
	public function params()
	{
		$vars = $this->search($this->EE->TMPL->fetch_param('search_id'));
		
		$data = array();
		
		foreach ($vars as $key => $value)
		{
			$data[] = array(
				'param_name' => $key,
				'param_value' => $value,
			);
		}
		
		return (count($data) > 0) ? $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $data) : $this->EE->TMPL->no_results();
	}
	
	/* FORM ACTIONS */
	
	public function form_submit()
	{
		//no, you can't access this method as an exp:tag
		if ( ! empty($this->EE->TMPL))
		{
			return;
		}
		
		if ( ! $this->EE->security->secure_forms_check($this->EE->input->post('XID')))
		{
			$this->EE->functions->redirect(stripslashes($this->EE->input->post('RET')));		
		}
		
		$return = $this->EE->input->post('return', TRUE);

		$secure_return = $this->EE->input->post('secure_return');
		
		foreach (array('ACT', 'XID', 'RET', 'site_id', 'return', 'submit', 'secure_return') as $key)
		{
			unset($_POST[$key]);
		}
		
		$_POST = $this->EE->security->xss_clean($_POST);
		
		//convert some of POST like arrays -> pipe delimited lists
		foreach ($_POST as $key => $value)
		{
			if (substr($key, 0, 7) === 'search:' && is_array($value))
			{
				foreach ($value as $_key => $_value)
				{
					//this is so we can keep 0 and '0', but get rid of '', NULL, and FALSE
					if ((string) $_value === '')
					{
						unset($value[$_key]);
					}
				}
				
				$_POST[$key] = implode('|', $value);
			}
		}
		
		if ($keywords = $this->EE->input->post('keywords'))
		{
			$this->EE->load->library('dynamo_cp', NULL, 'cp');
			
			$this->EE->load->helper('text');
			
			$this->EE->load->model('search_model');
			
			$search = array(
				'channel_id' => '',
				'cat_id' => '',
				'status' => '',
				'date_range' => '',	
				'author_id' => '',
				'search_in' => $this->EE->input->post('search_in') ? $this->EE->input->post('search_in') : 'body',
				'exact_match' => $this->EE->input->post('exact_match'),
				'keywords' => $keywords,
				'search_keywords' => ($this->EE->config->item('auto_convert_high_ascii') === 'y') ? ascii_to_entities($keywords) : $keywords,
				'_hook_wheres' => array(),
			);
			
			$data = $this->EE->search_model->build_main_query($search, array('title' => 'asc'), FALSE);
			
			if ($data['result_obj']->num_rows() === 0)
			{
				$_POST['entry_ids'] = array('X');
			}
			else
			{
				$_POST['entry_ids'] = array();
				
				foreach ($data['result_obj']->result() as $row)
				{
					$_POST['entry_ids'][] = $row->entry_id;
				}
			}
		}
		
		//clean, serialize, and encode the search parameter array for storage
		$parameters = base64_encode(serialize($_POST));
		
		//get matching search if it already exists
		$search_id = $this->EE->db->select('search_id')
					->from('dynamo')
					->where('parameters', $parameters)
					->get()
					->row('search_id');
		
		//generate a new search id
		if ( ! $search_id)
		{
			$search_id = $this->EE->functions->random('md5');
		
			$this->EE->db->insert('dynamo', array(
				'search_id' => $search_id,
				'date' => $this->EE->localize->now,
				'parameters' => $parameters,
			));
		}

		$return = $this->EE->functions->create_url(rtrim($return, '/').'/'.$search_id);

		if ($secure_return)
		{
			$return = str_replace('http://', 'https://', $return);
		}
		
		$this->EE->functions->redirect($return);
	}
	
	/* PRIVATE METHODS */
	
	private function search($search_id)
	{
		if ($search_id)
		{
			if (isset($this->EE->session->cache['dynamo'][$search_id]))
			{
				return $this->EE->session->cache['dynamo'][$search_id];
			}
			
			//cleanup searches more than a day old
			$this->EE->db->delete('dynamo', array('date <' => $this->EE->localize->now - 86400));
			
			$search_id = $this->EE->security->xss_clean($search_id);
			
			$query = $this->EE->db->select('parameters')
					->from('dynamo')
					->where('search_id', $search_id)
					->limit(1)
					->get();
			
			if ($query->num_rows())
			{
				//update search date
				$this->EE->db->update('dynamo', array('date' => $this->EE->localize->now), array('search_id' => $search_id));
				
				if ($parameters = @unserialize(base64_decode($query->row('parameters'))))
				{
					return $this->EE->session->cache['dynamo'][$search_id] = $parameters;
				}
			}
		}
		
		return array();
	}
}

/* End of file mod.dynamo.php */
/* Location: ./system/expressionengine/third_party/dynamo/mod.dynamo.php */