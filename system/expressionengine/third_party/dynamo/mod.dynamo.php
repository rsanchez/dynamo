<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Dynamo
{
	protected $search;
	protected $channel;
	
	public function __construct()
	{
		$this->EE =& get_instance();
		
		$this->EE->load->model('dynamo_model');
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
		
		$vars = $this->EE->dynamo_model->get_search($this->EE->TMPL->fetch_param('search_id'));
		
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
			$options = $this->EE->dynamo_model->get_options(array_keys($option_fields));
			
			foreach ($options as $field_name => $field_options)
			{
				if (is_null($field_options))
				{
					$vars['options:'.$field_name] = array(array());
				}
				else
				{
					$vars['options:'.$field_name] = $field_options;
				}
			}
		}
		
		if ( ! isset($vars['keywords']))
		{
			$vars['keywords'] = '';
		}
		
		foreach (array_keys($vars) as $key)
		{
			$vars['dynamo:'.$key] = $vars[$key];
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
		
		$_POST = array_merge($_POST, $this->EE->dynamo_model->get_search($this->EE->TMPL->fetch_param('search_id')));
		
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
		
		if (isset($_POST['entry_ids']))
		{
			$this->EE->TMPL->tagparams['entry_id'] = implode('|', $_POST['entry_ids']);
		}
		
		return $this->channel->entries();
	}
	
	public function params()
	{
		$vars = $this->EE->dynamo_model->get_search($this->EE->TMPL->fetch_param('search_id'));
		
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
	
	/**
	 * Options
	 *
	 * {exp:dynamo:options field="field_name" selected="some value"}
	 *    <option value="{option_value}"{selected}>{option_name}</option>
	 * {/exp:dynamo:options}
	 * 
	 * @return string
	 */
	public function options()
	{
		$variables = array_shift($this->EE->dynamo_model->get_options($this->EE->TMPL->fetch_param('field')));
		
		foreach ($variables as &$row)
		{
			$selected = $row['option_value'] === $this->EE->TMPL->fetch_param('selected');
			
			$row['selected'] = $selected ? ' selected="selected"' : '';
			$row['checked'] = $selected ? ' checked="checked"' : '';
		}
		
		return $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $variables);
	}
	
	/**
	 * Pre-fetch options
	 *
	 * Use prior to using many standalone {exp:dynamo:options} tags to avoid multiple options queries
	 * 
	 * @return string
	 */
	public function prefetch_options()
	{
		$this->EE->dynamo_model->get_options(explode('|', $this->EE->TMPL->fetch_params('fields')));
		
		return '';
	}
	
	public function statuses()
	{
		$channels = explode('|', $this->EE->TMPL->fetch_param('channel'));
		
		$include = $this->EE->TMPL->fetch_param('include') ? explode('|', $this->EE->TMPL->fetch_param('include')) : array();
		
		$exclude = $this->EE->TMPL->fetch_param('exclude') ? explode('|', $this->EE->TMPL->fetch_param('exclude')) : array();
		
		$result = $this->EE->dynamo_model->get_statuses($channels, $include, $exclude);
		
		$this->variable_prefix($result);
		
		return $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $result);
	}
	
	public function member_groups()
	{
		$include = $this->EE->TMPL->fetch_param('include') ? explode('|', $this->EE->TMPL->fetch_param('include')) : array();
		
		$exclude = $this->EE->TMPL->fetch_param('exclude') ? explode('|', $this->EE->TMPL->fetch_param('exclude')) : array();
		
		$result = $this->EE->dynamo_model->get_member_groups($include, $exclude);
		
		$this->variable_prefix($result);
		
		return $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $result);
	}
	
	public function increment()
	{
		$from = (int) $this->EE->TMPL->fetch_param('from', 1);
		
		$to = (int) $this->EE->TMPL->fetch_param('to', 0);
		
		$vars = array();
		
		for ($from; $from <= $to; $from++)
		{
			$vars[] = array(
				'increment' => $from,
			);
		}
		
		return $vars ? $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $vars) : '';
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
		
		if (AJAX_REQUEST && $this->EE->config->item('secure_forms') === 'y')
		{
			$this->EE->db->insert('security_hashes', array('date' => time() - 60, 'ip_address' => $this->EE->input->ip_address(), 'hash' => $this->EE->input->post('XID')));
		}
		
		$return = $this->EE->input->post('return', TRUE);
		
		$_POST = $this->EE->security->xss_clean($_POST);
		
		//convert some of POST like arrays -> pipe delimited lists
		foreach ($_POST as $key => $value)
		{
			if (substr($key, 0, 7) !== 'search:' && is_array($value))
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
		
		$parameters = array();
		
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
				$parameters['entry_ids'] = array('X');
			}
			else
			{
				$parameters['entry_ids'] = array();
				
				foreach ($data['result_obj']->result() as $row)
				{
					$parameters['entry_ids'][] = $row->entry_id;
				}
			}
		}
		
		//clean, serialize, and encode the search parameter array for storage
		
		$clean = array('ACT', 'XID', 'RET', 'site_id', 'return', 'submit');
		
		foreach($_POST as $key => $value)
		{
			if (in_array($key, $clean))
			{
				continue;
			}
			
			if ($value === "")
			{
				continue;
			}
			
			$parameters[$key] = $value;
		}
		
		$search_id = $this->EE->dynamo_model->create_search($parameters);
		
		$this->EE->functions->redirect(rtrim($return, '/').'/'.$search_id);
	}
	
	/* PRIVATE METHODS */
	
	protected function variable_prefix(&$variables)
	{
		if ( ! $this->EE->TMPL->fetch_param('variable_prefix'))
		{
			return;
		}
		
		foreach ($variables as &$row)
		{
			foreach (array_keys($row) as $key)
			{
				$row[$this->EE->TMPL->fetch_param('variable_prefix').$key] = $row[$key];
				
				unset($row[$key]);
			}
		}
	}
}

/* End of file mod.dynamo.php */
/* Location: ./system/expressionengine/third_party/dynamo/mod.dynamo.php */