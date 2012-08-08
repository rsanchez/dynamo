# Dynamo #

Makes Dynamic Parameters behave more like the Search module. See <http://expressionengine.com/user_guide/modules/channel/dynamic_parameters.html>

Until now, it was impossible to have your dynamic parameters persist when using pagination. To alleviate this, Dynamo takes your dynamic parameters and stores them in the database, and assigns a search_id, which can be used in to retrieve your filtered results, without having to perform another POST request with your parameters.

For more on filtering content with dynamic parameters, see this great tutorial: <http://eeinsider.com/articles/filtering-content-with-dynamic-parameters/>

## Installation

* Copy the /system/expressionengine/third_party/dynamo/ folder to your /system/expressionengine/third_party/ folder

## Form
Use this form to submit your dynamic parameters. It will add your search parameters to the database, and redirect you to your results template with the search_id. As of version 1.0.1, you can also submit arrays (like category[], for instance) which will automatically be converted into the pipe delimited format that entries parameters accept.

	{exp:dynamo:form return="site/entries"}
		<input type="text" name="search:your_custom_field">
		<select name="limit">
			<option value="10">10</option>
			<option value="20">20</option>
			<option value="50">50</option>
		</select>
		<select name="category[]" multiple="multiple">
			<option value="1"{if {exp:dynamo:selected value="1" in="{category}"} selected="selected"{/if}>Dogs</option>
			<option value="2"{if {exp:dynamo:selected value="2" in="{category}"} selected="selected"{/if}>Cats</option>
		</select>
		<input type="submit">
	{/exp:dynamo:form}

### Form Parameters

**return**

This is the location of your dynamo:entries template. The search_id will be added as the last segment.

	return="site/entries"

**search_id**

If you are displaying a form on your results page, and wish to show the submitted values.

	dynamic_parameters="search:your_custom_field|limit"

**prefix:your_field_name**

You can add a prefix to your dynamic parameter. There are two valid prefixes, `not ` and `=`.

`not ` is valid for: `entry_id`, `channel`, `category`, `search:your_field_name`, `group_id`, `status`, `url_title`, `username`, `author_id`, and `category_group`.

`=` is valid for `search:your_field_name` and triggers *exact* matching.

	{exp:dynamo:form prefix:channel="not" prefix:search:your_field_name="="}

**separator:your_field_name**

You can change the default separator, which is `|`, to `&` with this parameter. The `&` separator is only valid for `search:your_field_name` and `category`;

	{exp:dynamo:form separator:category="&"}

### Form Inputs

**keywords**

	<input type="text" name="keywords" value="{keywords}">
	
### Form Tags

Display options for the following fieldtypes: Select Dropdown, Multi Select, Radio Buttons, Checkboxes, Text, P&T Dropdown, P&T Multiselect, P&T Pill, P&T Radio Buttons, P&T Checkboxes, and P&T Switch.

	<select name="search:your_field_name">
	{options:your_field_name}
		<option value="{option_value}"{if search:your_field_name == option_value} selected="selected"{/if}>{option_name}</option>
	{/option:your_field_name}
	</select>
	

### Form Example

	{exp:dynamo:form return="site/entries" search_id="{segment_3}"}
		<input type="text" name="search:your_custom_field" value="{search:your_custom_field}">
		<select name="limit">
			<option value="10"{if limit == 10} selected="selected"{/if}>10</option>
			<option value="20"{if limit == 20} selected="selected"{/if}>20</option>
			<option value="50"{if limit == 50} selected="selected"{/if}>50</option>
		</select>
		<input type="submit">
	{/exp:dynamo:form}

## Entries
Use this tag in your results template to display the matching channel entries. It uses the same variables and parameters as a channel:entries tag, with the addition of the search_id parameter to fetch your matching results.

	{exp:dynamo:entries channel="entries" dynamic_parameters="search:your_custom_field|limit" search_id="{segment_3}"}
		<a href="{entry_id_path=site/entry}">{title}</a>
		{paginate}<p>Page {current_page} of {total_pages} pages {pagination_links}</p>{/paginate}
	{/exp:dynamo:entries}

## Form on Results Page
If you add a valid search_id to the form tag, it will inherit all of the search's dynamic parameters as tag variables.

	{exp:dynamo:form return="site/entries" search_id="{segment_3}"}
		<input type="text" name="search:your_custom_field" value="{search:your_custom_field}">
		<select name="limit">
			<option value="10"{if limit == 10} selected="selected"{/if}>10</option>
			<option value="20"{if limit == 20} selected="selected"{/if}>20</option>
			<option value="50"{if limit == 50} selected="selected"{/if}>50</option>
		</select>
		<input type="submit">
	{/exp:dynamo:form}
	
	{exp:dynamo:entries channel="entries" dynamic_parameters="search:your_custom_field|limit" search_id="{segment_3}"}
		<a href="{entry_id_path=site/entry}">{title}</a>
	{/exp:dynamo:entries}

## Helper Tags

### Options
Use this tag pair to display a dropdown in your form showing *all* valid options for the following fieldtypes: Text, Select Dropdown, Checkboxes, Multi Select, Radio Buttons, P&T Checkboxes, P&T Radio Buttons, P&T Dropdown, P&T Multiselect, P&T Pill, P&T Switch and Playa.

	<select name="your_field_name">
	{exp:dynamo:options field="your_field_name" selected="x"}
		<option value="{option_value}"{selected}>{option_name}</option>
	{/exp:dynamo:options}
	</select>

### Selected
Use this to show when a value is in an array of selections.
	
	{exp:dynamo:form channel="entries" dynamic_parameters="category" search_id="{segment_3}"}
		<select name="category[]">
		{exp:channel:categories channel="entries" style="linear"}
			<option value="{category_id}"{if {exp:dynamo:selected value="{category_id}" in="{category}"}} selected="selected"{/if}>{category_name}</option>
		{/exp:channel:entries}
		</select>
	{/exp:dynamo:form}

### Statuses
Use this tag pair to display valid statuses from the specified channel(s). The `include` and `exclude` parameters are optional.

	<select name="status">
	{exp:dynamo:statuses channel="entries" include="open" exclude="closed" variable_prefix="statuses:"}
		<option value="{statuses:status}"{if statuses:status == status} selected="selected"{/if}>{statuses:status}</option>
	{/exp:dynamo:statuses}
	</select>

### Member Groups
Use this tag pair to display valid member groups. The `include` and `exclude` parameters are optional.

	<select name="status">
	{exp:dynamo:member_groups include="1|5" exclude="6|7" variable_prefix="group:"}
		<option value="{group:group_id}"{if group:group_id == group_id} selected="selected"{/if}>{group:group_title}</option>
	{/exp:dynamo:member_groups}
	</select>
