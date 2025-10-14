<?php

namespace Wesanox\Booking\Boot\Data;

defined( 'ABSPATH' )|| exit;

class Handler
{
    protected $wpdb;
    private string $charset_collate;

    public function __construct($wpdb) {
        $this->wpdb = $wpdb;
        $this->charset_collate = $this->wpdb->get_charset_collate();
    }

    /**
     * @TODO implement this in a new way to get the migrations files in "here or there"
     */
//    /**
//     * @param string $table_name
//     * @param array $columns
//     * @param array $foreignKeys
//     * @return void
//     */
//    public function wesanox_create_table(string $table_name, array $columns, array $foreignKeys = []): void {
//        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
//
//        if( $this->wesanox_table_exists($table_name)) {
//            return;
//        }
//
//        $columns_sql = [];
//
//        foreach ($columns as $name => $type) {
//            $columns_sql[] = "$name $type";
//        }
//
//        $columns_sql[] = "PRIMARY KEY (id)";
//
//        foreach ($foreignKeys as $fk_column => $fk_target) {
//            $columns_sql[] = "FOREIGN KEY ($fk_column) REFERENCES $fk_target ON DELETE SET NULL ON UPDATE CASCADE";
//        }
//
//        $sql = "CREATE TABLE IF NOT EXISTS $table_name (" . implode(", ", $columns_sql) . ") $this->charset_collate;";
//
//        dbDelta($sql);
//    }
//
//    /**
//     * @param string $table_name
//     * @return void
//     */
//    public function wesanox_delete_table(string $table_name): void {
//        $this->wpdb->query("DROP TABLE IF EXISTS {$table_name}");
//    }
//
//    /**
//     * @param string $table_name
//     * @return bool
//     */
//    private function wesanox_table_exists(string $table_name): bool
//    {
//        $sql = $this->wpdb->prepare("SHOW TABLES LIKE %s", $table_name);
//        return $this->wpdb->get_var($sql) === $table_name;
//    }

    /**
     * @param string $table_name
     * @return mixed
     */
    public function wesanox_get_data(string $table_name)
    {
        return $this->wpdb->get_results("SELECT * FROM " . $table_name);
    }

    /**
     * @param int $id
     * @param string $table_name
     * @return mixed
     */
    public function wesanox_get_data_by_id(int $id, string $table_name)
    {
        return $this->wpdb->get_row("SELECT * FROM $table_name WHERE id = " . $id);
    }

    /**
     * @param string $mail
     * @param string $table_name
     * @return mixed
     */
    public function wesanox_get_data_by_mail(string $mail, string $table_name)
    {
        return $this->wpdb->get_row("SELECT * FROM $table_name WHERE account_mail = '" . $mail . "'");
    }

    /**
     * @param string $username
     * @param string $table_name
     * @return mixed
     */
    public function wesanox_get_data_by_username(string $username, string $table_name)
    {
        return $this->wpdb->get_row("SELECT * FROM $table_name WHERE account_username = '" . $username . "'");
    }

    /**
     * @param array $data
     * @param string $table
     * @return int
     */
    public function wesanox_insert_data(array $data, string $table) : int
    {
        $success = $this->wpdb->insert(
            $table,
            $data
        );

        if ($success) {
            return $this->wpdb->insert_id;
        }

        return 0;
    }

    /**
     * @param int $id
     * @param string $table
     * @return bool
     */
    public function wesanox_delete_data(int $id, string $table) : bool
    {
        return $this->wpdb->delete(
            $table,
            ['id' => $id],
        );
    }

    /**
     * @param array $data
     * @param int $id
     * @param string $table
     * @return bool
     */
    public function wesanox_update_data(array $data, int $id, string $table) : bool
    {
        return $this->wpdb->update(
            $table,
            $data,
            ['id' => $id],
        );
    }
}