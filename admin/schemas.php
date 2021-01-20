<?php

/**
 * DokuWiki Plugin struct (Admin Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr, Michael Große <dokuwiki@cosmocode.de>
 */

use dokuwiki\Form\Form;
use dokuwiki\plugin\struct\meta\CSVExporter;
use dokuwiki\plugin\struct\meta\CSVImporter;
use dokuwiki\plugin\struct\meta\CSVPageImporter;
use dokuwiki\plugin\struct\meta\CSVSerialImporter;
use dokuwiki\plugin\struct\meta\Schema;
use dokuwiki\plugin\struct\meta\SchemaBuilder;
use dokuwiki\plugin\struct\meta\SchemaEditor;
use dokuwiki\plugin\struct\meta\SchemaImporter;
use dokuwiki\plugin\struct\meta\StructException;

class admin_plugin_struct_schemas extends DokuWiki_Admin_Plugin
{

    /**
     * @return int sort number in admin menu
     */
    public function getMenuSort()
    {
        return 500;
    }

    /**
     * @return bool true if only access for superuser, false is for superusers and moderators
     */
    public function forAdminOnly()
    {
        return false;
    }

    /**
     * Should carry out any processing required by the plugin.
     */
    public function handle()
    {
        global $INPUT;
        global $ID;
        global $config_cascade;
        $config_file_path = end($config_cascade['main']['local']);

        // form submit
        $table = Schema::cleanTableName($INPUT->str('table'));
        if ($table && $INPUT->bool('save') && checkSecurityToken()) {
            $builder = new SchemaBuilder($table, $INPUT->arr('schema'));
            if (!$builder->build()) {
                msg('something went wrong while saving', -1);
            }
            touch(action_plugin_struct_cache::getSchemaRefreshFile());
        }
        // export
        if ($table && $INPUT->bool('export')) {
            $builder = new Schema($table);
            header('Content-Type: application/json');
            header("Content-Disposition: attachment; filename=$table.struct.json");
            echo $builder->toJSON();
            exit;
        }
        // import
        if ($table && $INPUT->bool('import')) {
            if (isset($_FILES['schemafile']['tmp_name'])) {
                $json = io_readFile($_FILES['schemafile']['tmp_name'], false);
                if (!$json) {
                    msg('Something went wrong with the upload', -1);
                } else {
                    $builder = new SchemaImporter($table, $json);
                    if (!$builder->build()) {
                        msg('something went wrong while saving', -1);
                    }
                    touch(action_plugin_struct_cache::getSchemaRefreshFile());
                }
            }
        }

        // import CSV
        if ($table && $INPUT->bool('importcsv')) {
            if (isset($_FILES['csvfile']['tmp_name'])) {
                try {
                    $datatype = $INPUT->str('importtype');
                    if ($datatype === CSVExporter::DATATYPE_PAGE) {
                        $csvImporter = new CSVPageImporter($table, $_FILES['csvfile']['tmp_name'], $datatype);
                    } else if ($datatype === CSVExporter::DATATYPE_SERIAL) {
                        $csvImporter = new CSVSerialImporter($table, $_FILES['csvfile']['tmp_name'], $datatype);
                    } else {
                        $csvImporter = new CSVImporter($table, $_FILES['csvfile']['tmp_name'], $datatype);
                    }
                    $csvImporter->import();
                    msg($this->getLang('admin_csvdone'), 1);
                } catch (StructException $e) {
                    msg(hsc($e->getMessage()), -1);
                }
            }
        }

        // export CSV
        if ($table && $INPUT->bool('exportcsv')) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $table . '.csv";');
            new CSVExporter($table, $INPUT->str('exporttype'));
            exit();
        }

        // delete
        if ($table && $INPUT->bool('delete')) {
            if ($table != $INPUT->str('confirm')) {
                msg($this->getLang('del_fail'), -1);
            } else {
                try {
                    $schema = new Schema($table);
                    $schema->delete();
                    msg($this->getLang('del_ok'), 1);
                    touch(action_plugin_struct_cache::getSchemaRefreshFile());
                    send_redirect(wl($ID, array('do' => 'admin', 'page' => 'struct_schemas'), true, '&'));
                } catch (StructException $e) {
                    msg(hsc($e->getMessage()), -1);
                }
            }
        }

        // clear
        if ($table && $INPUT->bool('clear')) {
            if ($table != $INPUT->str('confirm_clear')) {
                msg($this->getLang('clear_fail'), -1);
            } else {
                try {
                    $schema = new Schema($table);
                    $schema->clear();
                    msg($this->getLang('clear_ok'), 1);
                    touch(action_plugin_struct_cache::getSchemaRefreshFile());
                    send_redirect(wl($ID, array('do' => 'admin', 'page' => 'struct_schemas'), true, '&'));
                } catch (StructException $e) {
                    msg(hsc($e->getMessage()), -1);
                }
            }
        }
    }

    /**
     * Render HTML output, e.g. helpful text and a form
     */
    public function html()
    {
        global $INPUT;

        $table = Schema::cleanTableName($INPUT->str('table'));
        if ($table) {
            $schema = new Schema($table, 0);

            echo $this->locale_xhtml('editor_edit');
            echo '<h2>' . sprintf($this->getLang('edithl'), hsc($table)) . '</h2>';

            echo '<ul class="tabs" id="plugin__struct_tabs">';
            /** @noinspection HtmlUnknownAnchorTarget */
            echo '<li class="active"><a href="#plugin__struct_editor">' . $this->getLang('tab_edit') . '</a></li>';
            /** @noinspection HtmlUnknownAnchorTarget */
            echo '<li><a href="#plugin__struct_json">' . $this->getLang('tab_export') . '</a></li>';
            /** @noinspection HtmlUnknownAnchorTarget */
            echo '<li><a href="#plugin__struct_delete">' . $this->getLang('tab_delete') . '</a></li>';
            echo '</ul>';
            echo '<div class="panelHeader"></div>';

            $editor = new SchemaEditor($schema);
            echo $editor->getEditor();
            echo $this->htmlJson($schema);
            echo $this->htmlDelete($schema);
        } else {
            echo $this->locale_xhtml('editor_intro');
            echo $this->htmlNewschema();
        }
    }

    /**
     * Form for handling import/export from/to JSON and CSV
     *
     * @param Schema $schema
     * @return string
     */
    protected function htmlJson(Schema $schema)
    {
        $form = new Form(array('enctype' => 'multipart/form-data', 'id' => 'plugin__struct_json'));
        $form->setHiddenField('do', 'admin');
        $form->setHiddenField('page', 'struct_schemas');
        $form->setHiddenField('table', $schema->getTable());

        // schemas
        $form->addFieldsetOpen($this->getLang('export'));
        $form->addButton('export', $this->getLang('btn_export'));
        $form->addFieldsetClose();

        $form->addFieldsetOpen($this->getLang('import'));
        $form->addElement(new \dokuwiki\Form\InputElement('file', 'schemafile'))->attr('accept', '.json');
        $form->addButton('import', $this->getLang('btn_import'));
        $form->addHTML('<p>' . $this->getLang('import_warning') . '</p>');
        $form->addFieldsetClose();

        // data
        $form->addFieldsetOpen($this->getLang('admin_csvexport'));
        $form->addTagOpen('legend');
        $form->addHTML($this->getLang('admin_csvexport_datatype'));
        $form->addTagClose('legend');
        $form->addRadioButton('exporttype', $this->getLang('admin_csv_page'))
            ->val(CSVExporter::DATATYPE_PAGE)
            ->attr('checked', 'checked')->addClass('edit block');
        $form->addRadioButton('exporttype', $this->getLang('admin_csv_lookup'))
            ->val(CSVExporter::DATATYPE_GLOBAL)
            ->addClass('edit block');
        $form->addRadioButton('exporttype', $this->getLang('admin_csv_serial'))
            ->val(CSVExporter::DATATYPE_SERIAL)
            ->addClass('edit block');
        $form->addHTML('<br>');
        $form->addButton('exportcsv', $this->getLang('btn_export'));
        $form->addFieldsetClose();

        $form->addFieldsetOpen($this->getLang('admin_csvimport'));
        $form->addTagOpen('legend');
        $form->addHTML($this->getLang('admin_csvimport_datatype'));
        $form->addTagClose('legend');
        $form->addRadioButton('importtype', $this->getLang('admin_csv_page'))
            ->val(CSVExporter::DATATYPE_PAGE)
            ->attr('checked', 'checked')
            ->addClass('edit block');
        $form->addRadioButton('importtype', $this->getLang('admin_csv_lookup'))
            ->val(CSVExporter::DATATYPE_GLOBAL)
            ->addClass('edit block');
        $form->addRadioButton('importtype', $this->getLang('admin_csv_serial'))
            ->val(CSVExporter::DATATYPE_SERIAL)
            ->addClass('edit block');
        $form->addHTML('<br>');
        $form->addElement(new \dokuwiki\Form\InputElement('file', 'csvfile'))->attr('accept', '.csv');
        $form->addButton('importcsv', $this->getLang('btn_import'));
        $form->addCheckbox('createPage', 'Create missing pages')->addClass('block edit');
        $form->addHTML('<p><a href="https://www.dokuwiki.org/plugin:struct:csvimport">' . $this->getLang('admin_csvhelp') . '</a></p>');
        $form->addFieldsetClose();

        return $form->toHTML();
    }

    /**
     * Form for deleting schemas
     *
     * @param Schema $schema
     * @return string
     */
    protected function htmlDelete(Schema $schema)
    {
        $form = new Form(array('id' => 'plugin__struct_delete'));
        $form->setHiddenField('do', 'admin');
        $form->setHiddenField('page', 'struct_schemas');
        $form->setHiddenField('table', $schema->getTable());

        $form->addFieldsetOpen($this->getLang('btn_delete'));
        $form->addHTML($this->locale_xhtml('delete_intro'));
        $form->addTextInput('confirm', $this->getLang('del_confirm'));
        $form->addButton('delete', $this->getLang('btn_delete'));
        $form->addFieldsetClose();

        $form->addFieldsetOpen($this->getLang('btn_clear'));
        $form->addHTML($this->locale_xhtml('clear_intro'));
        $form->addTextInput('confirm_clear', $this->getLang('clear_confirm'));
        $form->addButton('clear', $this->getLang('btn_clear'));
        $form->addFieldsetClose();

        return $form->toHTML();
    }

    /**
     * Form to add a new schema
     *
     * @return string
     */
    protected function htmlNewschema()
    {
        $form = new Form();
        $form->addClass('struct_newschema');
        $form->addFieldsetOpen($this->getLang('create'));
        $form->setHiddenField('do', 'admin');
        $form->setHiddenField('page', 'struct_schemas');
        $form->addTextInput('table', $this->getLang('schemaname'));
        $form->addButton('', $this->getLang('save'));
        $form->addHTML('<p>' . $this->getLang('createhint') . '</p>'); // FIXME is that true? we probably could
        $form->addFieldsetClose();
        return $form->toHTML();
    }

    /**
     * Adds all available schemas to the Table of Contents
     *
     * @return array
     */
    public function getTOC()
    {
        global $ID;

        $toc = array();
        $link = wl(
            $ID,
            array(
                   'do' => 'admin',
                   'page' => 'struct_assignments'
               )
        );
        $toc[] = html_mktocitem($link, $this->getLang('menu_assignments'), 0, '');
        $slink = wl(
            $ID,
            array(
                   'do' => 'admin',
                   'page' => 'struct_schemas'
               )
        );
        $toc[] = html_mktocitem($slink, $this->getLang('menu'), 0, '');

        $tables = Schema::getAll();
        if ($tables) {
            foreach ($tables as $table) {
                $link = wl(
                    $ID,
                    array(
                           'do' => 'admin',
                           'page' => 'struct_schemas',
                           'table' => $table
                       )
                );

                $toc[] = html_mktocitem($link, hsc($table), 1, '');
            }
        }

        return $toc;
    }
}

// vim:ts=4:sw=4:et:
