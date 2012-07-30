<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

//spoofs the CP lib so the ever-so-userful search_model can function on the front-end
class Dynamo_cp
{
	public function __construct()
	{
		$this->EE =& get_instance();
	}
	
	public function allowed_group()
	{
		if (func_num_args() === 0)
		{
			return FALSE;
		}
		
		if ($this->EE->session->userdata('group_id') == 1)
		{
			return TRUE;
		}
		
		foreach (func_get_args() as $key)
		{
			//need this for search to work properly
			if ($key === 'can_view_other_entries')
			{
				return TRUE;
			}
			
			$allowed = $this->EE->session->userdata($key);
			
			if ( ! $allowed || $allowed !== 'y')
			{
				return FALSE;
			}
		}
		
		return TRUE;
	}
}

/* End of file mod.dynamo.php */
/* Location: ./system/expressionengine/third_party/dynamo/mod.dynamo.php */