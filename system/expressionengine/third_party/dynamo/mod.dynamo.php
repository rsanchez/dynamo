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
		if ( ! $this->EE->TMPL->fetch_param('return'))
		{
			$this->EE->output->show_user_error('general', $this->EE->lang->line('no_return'));
		}
		
		$this->EE->load->helper(array('form', 'url'));
		
		$form = array(
			'hidden_fields' => array(
				'ACT' => $this->EE->functions->fetch_action_id('Dynamo', 'form_submit'),
				'RET' => current_url(),
				'return' => $this->EE->TMPL->fetch_param('return'),
			),
		);
		
		foreach (array('id', 'class', 'onsubmit', 'name') as $key)
		{
			if ($this->EE->TMPL->fetch_param($key))
			{
				$form[$key] = $this->EE->TMPL->fetch_param($key);
			}
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
		
		/*
		//added so you could do array checkboxes with {if selected:category == 10}{/if}
		if (preg_match_all('/{\s*if\s+selected:(.*?)\s*(==|!=)\s*([\042\047])?(.*?)\\1?\s*}(.*?){\/if\s*}/s', $this->EE->TMPL->tagdata, $matches))
		{
			foreach ($matches[0] as $i => $replace)
			{
				$field = $matches[1][$i];
				$operator = $matches[2][$i];
				$is = $matches[4][$i];
				$tagdata = $matches[5][$i];
				
				if (isset($vars[$field]))
				{
					$array = (strpos($vars[$field], '|') !== FALSE) ? explode('|', $vars[$field]) : array($vars[$field]);
					
					$cond = in_array($is, $array);
					
					if ($operator === '!=')
					{
						$cond = ! $cond;
					}
					
					$tagdata = ($cond) ? $tagdata : '';
				}
				else
				{
					$tagdata = '';
				}
				
				$this->EE->TMPL->tagdata = str_replace($replace, $tagdata, $this->EE->TMPL->tagdata);
			}
		}
		*/
		
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
		
		foreach (array('ACT', 'XID', 'RET', 'site_id', 'return', 'submit') as $key)
		{
			unset($_POST[$key]);
		}
		
		//convert some of POST like arrays -> pipe delimited lists
		foreach ($_POST as $key => $value)
		{
			if (substr($key, 0, 7) !== 'search:')
			{
				if (is_array($value))
				{
					$_POST[$key] = implode('|', $value);
				}
			}
		}
		
		//clean, serialize, and encode the search parameter array for storage
		$parameters = base64_encode(serialize($this->EE->security->xss_clean($_POST)));
		
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
		
		if (substr($return, -1) != '/')
		{
			$return = $return.'/';
		}
		
		$this->EE->functions->redirect($return.$search_id);
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