<?php

namespace tool_ally\testing\traits;

defined('MOODLE_INTERNAL') || die;

trait component_assertions {

    /**
     * @param array $items
     * @param int $id
     * @param string $component
     * @param string $table
     * @param string $field
     */
    protected function assert_content_items_contain_item(array $items, $id, $component, $table, $field) {
        if (empty($items)) {
            $this->fail('Content items list is empty!');
        }
        foreach ($items as $item) {
            if (intval($item->id) === intval($id) && $item->component === $component &&
                $item->table === $table && $item->field === $field) {
                return;
            }
        }
        $compref = 'id "'.$id.'" component "'.$component. '" table "'.$table.'" and field "'.$field.'"';
        $this->fail('Content items list does not contain item with '.$compref);
    }

    /**
     * @param array $items
     * @param int $id
     * @param string $component
     * @param string $table
     * @param string $field
     */
    protected function assert_content_items_not_contain_item(array $items, $id, $component, $table, $field) {
        foreach ($items as $item) {
            if (intval($item->id) === intval($id) && $item->component === $component &&
                $item->table === $table && $item->field === $field) {
                $compref = 'id "'.$id.'" component "'.$component. '" table "'.$table.'" and field "'.$field.'"';
                $this->fail('Content items list should not contain item with '.$compref);
            }
        }
    }
}