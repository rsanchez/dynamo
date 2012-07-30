<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Dynamo_model extends CI_Model
{
	public function get_statuses($channels, $include = array(), $exclude = array())
	{
		if ($include)
		{
			$this->db->where_in('status', $include);
		}
		
		if ($exclude)
		{
			$this->db->where_not_in('status', $exclude);
		}
		
		$query = $this->db->select('statuses.*')
				->where_in('channel_name', $channels)
				->join('channels', 'channels.status_group = statuses.group_id')
				->get('statuses');
		
		$result = $query->result_array();
		
		$query->free_result();
		
		return $result;
	}
	
	public function get_member_groups($include = array(), $exclude = array())
	{
		if ($include)
		{
			$this->db->where_in('group_id', $include);
		}
		
		if ($exclude)
		{
			$this->db->where_not_in('group_id', $exclude);
		}
		
		$query = $this->db->where('site_id', $this->config->item('site_id'))
					->get('member_groups');
		
		$result = $query->result_array();
		
		$query->free_result();
		
		return $result;
	}
	
	public function create_search($parameters)
	{
		$parameters = base64_encode(serialize($parameters));
		
		//get matching search if it already exists
		$query = $this->db->select('search_id')
					->from('dynamo')
					->where('parameters', $parameters)
					->get();
		
		//generate a new search id
		if ($query->num_rows() === 0)
		{
			$search_id = $this->functions->random('md5');
		
			$this->db->insert('dynamo', array(
				'search_id' => $search_id,
				'date' => $this->localize->now,
				'parameters' => $parameters,
			));
		}
		else
		{
			$search_id = $query->row('search_id');
		}
		
		return $search_id;
	}
	
	public function get_search($search_id)
	{
		if ( ! $search_id || strlen($search_id) !== 32)
		{
			return array();
		}
		
		if (isset($this->session->cache['dynamo'][$search_id]))
		{
			return $this->session->cache['dynamo'][$search_id];
		}
		
		//cleanup searches more than a day old
		$this->db->delete('dynamo', array('date <' => $this->localize->now - 86400));
		
		$search_id = $this->security->xss_clean($search_id);
		
		$query = $this->db->select('parameters')
				->from('dynamo')
				->where('search_id', $search_id)
				->limit(1)
				->get();
		
		$parameters = $query->row('parameters');
		
		$query->free_result();
		
		if ( ! $parameters)
		{
			return array();
		}
		
		//update search date
		$this->db->update('dynamo', array('date' => $this->localize->now), array('search_id' => $search_id));
		
		return $this->session->cache['dynamo'][$search_id] = (array) @unserialize(base64_decode($parameters));
	}
	
	/**
	 * Get field options
	 *
	 * 	array(
	 * 		'field_name' => array(
	 * 			array(
	 * 				array('option_value' => 'foo', 'option_name' => 'bar'),
	 * 				array('option_value' => 'foo2', 'option_name' => 'bar2')
	 * 			)
	 * 			array(
	 * 				array('option_value' => 'foo', 'option_name' => 'bar'),
	 * 				array('option_value' => 'foo2', 'option_name' => 'bar2')
	 * 			)
	 * 		)
	 * 		'field_name2' => array(
	 * 			array(
	 * 				array('option_value' => 'foo', 'option_name' => 'bar'),
	 * 				array('option_value' => 'foo2', 'option_name' => 'bar2')
	 * 			)
	 * 			array(
	 * 				array('option_value' => 'foo', 'option_name' => 'bar'),
	 * 				array('option_value' => 'foo2', 'option_name' => 'bar2')
	 * 			)
	 * 		)
	 * 	)
	 * 
	 * @param string|array $field_name a field name or an array of field names
	 * 
	 * @return array
	 */
	public function get_options($field_names)
	{
		static $cache = array();
		
		if ( ! is_array($field_names))
		{
			$field_names = array($field_names);
		}
		
		$keys = array_flip($field_names);
		
		//all the fields are in the cache
		if ( ! array_diff_key($keys, $cache))
		{
			return array_intersect_key($cache, $keys);
		}
		
		$this->db->where_in('field_name', $field_names);
		
		$options = array();
		
		$query = $this->db->select('field_name, field_type, field_list_items, field_settings, field_id')
					->get('channel_fields');
		
		foreach ($query->result() as $row)
		{
			$field_options = array();
			
			switch($row->field_type)
			{
				case 'pt_checkboxes':
				case 'pt_radio_buttons':
				case 'pt_dropdown':
				case 'pt_multiselect':
				case 'pt_pill':
					
					$field_settings = @unserialize(base64_decode($row->field_settings));
					
					if (isset($field_settings['options']))
					{
						foreach ($field_settings['options'] as $option_value => $option_name)
						{
							$field_options[] = array(
								'option_value' => $option_value,
								'option_name' => $option_name,
							);
						}
					}
					
					break;
				case 'pt_switch':
					
					$field_settings = @unserialize(base64_decode($row->field_settings));
					
					if (is_array($field_settings))
					{
						$field_options = array(
							array(
								'option_value' => $field_settings['off_val'],
								'option_name' => $field_settings['off_label'],
							),
							array(
								'option_value' => $field_settings['on_val'],
								'option_name' => $field_settings['on_label'],
							),
						);
					}
					
					break;
				case 'text':
					
					$channel_query = $this->db->distinct()
									->select('field_id_'.$row->field_id)
									->where('field_id_'.$row->field_id.' !=', '')
									->get('channel_data');
					
					foreach ($channel_query->result() as $row)
					{
						$field_options[] = array(
							'option_value' => $row->{'field_id_'.$row->field_id},
							'option_name' => $row->{'field_id_'.$row->field_id},
						);
					}
					
					$channel_query->free_result();
					
					break;
				
				case 'playa':
					
					$field_settings = @unserialize(base64_decode($row->field_settings));
					
					$field_settings = array_merge(array(
						'expired' => 'n',
						'future' => 'y',
						'channels' => array(),
						'cats' => array(),
						'authors' => array(),
						'statuses' => array(),
						'sort' => 'ASC',
						'orderby'  => 'title',
						'limit'    => '0',
						'limitby'  => '',
						'multi'    => 'y',
					), $field_settings);
					
					require_once PATH_THIRD.'playa/helper.php';
					
					$helper = new Playa_Helper;
				
					$params = array(
						'show_expired' => $field_settings['expired'] === 'y' ? 'yes' : '',
						'show_future_entries' => $field_settings['future'] === 'y' ? 'yes' : '',
						'channel_id' => $field_settings['channels'],
						'category' => $field_settings['cats'],
						'author_id' => $field_settings['authors'],
						'status' => $field_settings['statuses']
					);
		
					if ($field_settings['limit'])
					{
						$params['orderby'] = 'entry_date';
						$params['sort'] = $field_settings['limitby'] === 'newest' ? 'DESC' : 'ASC';
						$params['limit'] = $field_settings['limit'];
					}
					else
					{
						$params['orderby'] = $field_settings['orderby'];
						$params['sort'] = $field_settings['sort'];
					}
		
					$entries = $helper->entries_query($params);
					
					if ($field_settings['limitby'])
					{
						$helper->sort_entries($entries, $field_settings['sort'], $field_settings['orderby']);
					}
					
					foreach ($entries as $entry)
					{
						$field_options[] = array(
							'option_value' => $entry->title,
							'option_name' => $entry->title,
						);
					}
					
					break;
				
				default:
				
					if ($row->field_list_items)
					{
						foreach (preg_split('/[\r\n]+/', $row->field_list_items) as $option_value)
						{
							$field_options[] = array(
								'option_value' => $option_value,
								'option_name' => $option_value,
							);
						}
					}
					
					break;
			}
			
			$cache[$row->field_name] = $options[$row->field_name] = $field_options;
		}
		
		$query->free_result();
		
		return $options;
	}
}

/* End of file mod.dynamo.php */
/* Location: ./system/expressionengine/third_party/dynamo/mod.dynamo.php */