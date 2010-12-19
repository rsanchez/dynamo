# Dynamo #

Makes Dynamic Parameters behave more like the Search module. See <http://expressionengine.com/user_guide/modules/channel/dynamic_parameters.html>

Until now, it was impossible to have your dynamic parameters persist when using pagination. To alleviate this, Dynamo takes your dynamic parameters and stores them in the database, and assigns a search_id, which can be used in to retrieve your filtered results, without having to perform another POST request with your parameters.

For more on filtering content with dynamic parameters, see this great tutorial: <http://eeinsider.com/articles/filtering-content-with-dynamic-parameters/>

## Installation

* Copy the /system/expressionengine/third_party/dynamo/ folder to your /system/expressionengine/third_party/ folder

## Form
Use this form to submit your dynamic parameters. It will add your search parameters to the database, and redirect you to your results template with the search_id.

	{exp:dynamo:form return="site/entries"}
		<input type="text" name="search:your_custom_field">
		<select name="limit">
			<option value="10">10</option>
			<option value="20">20</option>
			<option value="50">50</option>
		</select>
		<input type="submit">
	{/exp:dynamo:form}

## Form Parameters

**return**
This is the location of your dynamo:entries template. The search_id will be added as the last segment.
	return="site/entries"

**search_id**
If you are displaying a form on your results page, and wish to show the submitted values.
	dynamic_parameters="search:your_custom_field|limit"

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