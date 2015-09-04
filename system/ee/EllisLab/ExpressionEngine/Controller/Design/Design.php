<?php

namespace EllisLab\ExpressionEngine\Controller\Design;

use ZipArchive;
use EllisLab\ExpressionEngine\Library\CP\Table;

use EllisLab\ExpressionEngine\Library\Data\Collection;
use EllisLab\ExpressionEngine\Controller\Design\AbstractDesign as AbstractDesignController;

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2003 - 2015, EllisLab, Inc.
 * @license		https://ellislab.com/expressionengine/user-guide/license.html
 * @link		http://ellislab.com
 * @since		Version 3.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * ExpressionEngine CP Design Class
 *
 * @package		ExpressionEngine
 * @subpackage	Control Panel
 * @category	Control Panel
 * @author		EllisLab Dev Team
 * @link		http://ellislab.com
 */
class Design extends AbstractDesignController {

	public function index()
	{
		$this->manager();
	}

	public function export()
	{
		$templates = ee('Model')->get('Template')
			->fields('template_id')
			->filter('site_id', ee()->config->item('site_id'));

		if (ee()->session->userdata['group_id'] != 1)
		{
			$templates->filter('group_id', 'IN', array_keys(ee()->session->userdata['assigned_template_groups']));
		}

		$template_ids = $templates->all()
			->pluck('template_id');

		$this->exportTemplates($template_ids);
	}

	public function manager($group_name = NULL)
	{
		if (is_null($group_name))
		{
			$group = ee('Model')->get('TemplateGroup')
				->filter('is_site_default', 'y')
				->filter('site_id', ee()->config->item('site_id'))
				->first();

			if ( ! $group)
			{
				$group = ee('Model')->get('TemplateGroup')
					->filter('site_id', ee()->config->item('site_id'))
					->order('group_name', 'asc')
					->first();
			}

			if ( ! $group)
			{
				ee()->functions->redirect(ee('CP/URL', 'design/system'));
			}
		}
		else
		{
			$group = ee('Model')->get('TemplateGroup')
				->filter('group_name', $group_name)
				->filter('site_id', ee()->config->item('site_id'))
				->first();

			if ( ! $group)
			{
				show_error(sprintf(lang('error_no_template_group'), $group_name));
			}
		}

		if ( ! $this->hasEditTemplatePrivileges($group->group_id))
		{
			show_error(lang('unauthorized_access'));
		}

		if (ee()->input->post('bulk_action') == 'remove')
		{
			if ($this->hasEditTemplatePrivileges($group->group_id))
			{
				$this->remove(ee()->input->post('selection'));
				ee()->functions->redirect(ee('CP/URL', 'design/manager/' . $group_name, ee()->cp->get_url_state()));
			}
			else
			{
				show_error(lang('unauthorized_access'));
			}
		}
		elseif (ee()->input->post('bulk_action') == 'export')
		{
			$this->export(ee()->input->post('selection'));
		}

		$this->_sync_from_files();

		$vars = array();

		$vars['show_new_template_button'] = TRUE;
		$vars['group_id'] = $group->group_name;

		$base_url = ee('CP/URL', 'design/manager/' . $group->group_name);

		$table = $this->buildTableFromTemplateCollection($group->Templates);

		$vars['table'] = $table->viewData($base_url);
		$vars['form_url'] = $vars['table']['base_url'];

		if ( ! empty($vars['table']['data']))
		{
			// Paginate!
			$vars['pagination'] = ee('CP/Pagination', $vars['table']['total_rows'])
				->perPage($vars['table']['limit'])
				->currentPage($vars['table']['page'])
				->render($base_url);
		}

		ee()->javascript->set_global('template_settings_url', ee('CP/URL', 'design/template/settings/###')->compile());
		ee()->javascript->set_global('lang.remove_confirm', lang('template') . ': <b>### ' . lang('templates') . '</b>');
		ee()->cp->add_js_script(array(
			'file' => array(
				'cp/confirm_remove',
				'cp/design/manager'
			),
		));

		$this->generateSidebar($group->group_id);
		$this->stdHeader();
		ee()->view->cp_page_title = lang('template_manager');
		ee()->view->cp_heading = sprintf(lang('templates_in_group'), $group->group_name);

		ee()->cp->render('design/index', $vars);
	}

	private function remove($template_ids)
	{
		if ( ! ee()->cp->allowed_group('can_delete_templates'))
		{
			show_error(lang('unauthorized_access'));
		}

		if ( ! is_array($template_ids))
		{
			$template_ids = array($template_ids);
		}

		$template_names = array();
		$templates = ee('Model')->get('Template', $template_ids)
			->filter('site_id', ee()->config->item('site_id'))
			->all();

		foreach ($templates as $template)
		{
			$template_names[] = $template->getTemplateGroup()->group_name . '/' . $template->template_name;
		}

		$templates->delete();

		ee('CP/Alert')->makeInline('shared-form')
			->asSuccess()
			->withTitle(lang('success'))
			->addToBody(lang('templates_removed_desc'))
			->addToBody($template_names)
			->defer();
	}

	protected function _sync_from_files()
	{
		if (ee()->config->item('save_tmpl_files') != 'y' || ee()->config->item('tmpl_file_basepath') == '')
		{
			return FALSE;
		}

		ee()->load->library('api');
		ee()->legacy_api->instantiate('template_structure');

		$groups = ee('Model')->get('TemplateGroup')->with('Templates')->all();
		$group_ids_by_name = $groups->getDictionary('group_name', 'group_id');

		$existing = array();

		foreach ($groups as $group)
		{
			$existing[$group->group_name.'.group'] = array_combine(
				$group->Templates->pluck('template_name'),
				$group->Templates->pluck('template_name')
			);
		}

		$basepath = ee()->config->slash_item('tmpl_file_basepath');
		$basepath .= '/'.ee()->config->item('site_short_name');
		ee()->load->helper('directory');
		$files = directory_map($basepath, 0, 1);

		if ($files !== FALSE)
		{
			foreach ($files as $group => $templates)
			{
				if (substr($group, -6) != '.group')
				{
					continue;
				}

				$group_name = substr($group, 0, -6); // remove .group

				// DB column limits template and group name to 50 characters
				if (strlen($group_name) > 50)
				{
					continue;
				}

				$group_id = '';

				if ( ! preg_match("#^[a-zA-Z0-9_\-]+$#i", $group_name))
				{
					continue;
				}

				// if the template group doesn't exist, make it!
				if ( ! isset($existing[$group]))
				{
					if ( ! ee()->legacy_api->is_url_safe($group_name))
					{
						continue;
					}

					if (in_array($group_name, array('act', 'css')))
					{
						continue;
					}

					$data = array(
						'group_name'		=> $group_name,
						'is_site_default'	=> 'n',
						'site_id'			=> ee()->config->item('site_id')
					);

					$new_group = ee('Model')->make('TemplateGroup', $data)->save();
					$group_id = $new_group->group_id;
				}

				// Grab group_id if we still don't have it.
				if ($group_id == '')
				{
					$group_id = $group_ids_by_name[$group_name];
				}

				// if the templates don't exist, make 'em!
				foreach ($templates as $template)
				{
					// Skip subdirectories (such as those created by svn)
					if (is_array($template))
					{
						continue;
					}
					// Skip hidden ._ files
					if (substr($template, 0, 2) == '._')
					{
						continue;
					}
					// If the last occurance is the first position?  We skip that too.
					if (strrpos($template, '.') == FALSE)
					{
						continue;
					}
					$ext = strtolower(ltrim(strrchr($template, '.'), '.'));
					if ( ! in_array('.'.$ext, ee()->api_template_structure->file_extensions))
					{
						continue;
					}

					$ext_length = strlen($ext) + 1;
					$template_name = substr($template, 0, -$ext_length);
					$template_type = array_search('.'.$ext, ee()->api_template_structure->file_extensions);

					if (isset($existing[$group][$template_name]))
					{
						continue;
					}

					if ( ! ee()->legacy_api->is_url_safe($template_name))
					{
						continue;
					}

					if (strlen($template_name) > 50)
					{
						continue;
					}

					$data = array(
						'group_id'				=> $group_id,
						'template_name'			=> $template_name,
						'template_type'			=> $template_type,
						'template_data'			=> file_get_contents($basepath.'/'.$group.'/'.$template),
						'edit_date'				=> ee()->localize->now,
						'last_author_id'		=> ee()->session->userdata['member_id'],
						'site_id'				=> ee()->config->item('site_id')
					 );

					// do it!
					ee('Model')->make('Template', $data)->save();

					// add to existing array so we don't try to create this template again
					$existing[$group][] = $template_name;
				}
				// An index template is required- so we create it if necessary
				if ( ! isset($existing[$group]['index']))
				{
					$data = array(
						'group_id'				=> $group_id,
						'template_name'			=> 'index',
						'template_data'			=> '',
						'edit_date'				=> ee()->localize->now,
						'save_template_file'	=> 'y',
						'last_author_id'		=> ee()->session->userdata['member_id'],
						'site_id'				=> ee()->config->item('site_id')
					 );

					ee('Model')->make('Template', $data)->save();
				}

				unset($existing[$group]);
			}
		}
	}
}
// EOF
