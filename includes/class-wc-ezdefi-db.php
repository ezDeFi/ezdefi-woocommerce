<?php

defined( 'ABSPATH' ) or exit;

class WC_Ezdefi_Db
{
	const OPTION = 'woocommerce_ezdefi_settings';

	/**
	 * Get plugin options
	 *
	 * @return array
	 */
	public function get_options()
	{
		return get_option( self::OPTION );
	}

	/**
	 * Get plugin option
	 *
	 * @param $key
	 *
	 * @return string|array
	 */
	public function get_option( $key )
	{
		$option = get_option( self::OPTION );

		if( ! isset( $option[$key] ) || $option[$key] === '' ) {
			return '';
		}

		return $option[$key];
	}

	/**
	 * Get currency data option
	 *
	 * @return array
	 */
	public function get_currency_data()
	{
		return $this->get_option( 'currency' );
	}

	/**
	 * Get currency option by symbol
	 *
	 * @param $symbol
	 *
	 * @return bool|mixed
	 */
	public function get_currency_option( $symbol )
	{
		$currency_data = $this->get_currency_data();

		$index = array_search( $symbol, array_column( $currency_data, 'symbol' ) );

		if( $index === false ) {
			return null;
		}

		return $currency_data[$index];
	}

	/**
	 * Get Gateway API Url
	 *
	 * @return string
	 */
	public function get_api_url()
	{
		return $this->get_option( 'api_url' );
	}

	/**
	 * Get Gateway API Key
	 *
	 * @return string
	 */
	public function get_api_key()
	{
		return $this->get_option( 'api_key' );
	}

	/**
	 * Generate smallest & unique amount id
	 *
	 * @param float $price
	 * @param array $currency_data
	 *
	 * @return float
	 */
	public function generate_amount_id( $price, $currency_data )
	{
		global $wpdb;

		$decimal = $currency_data['decimal'];
		$symbol = $currency_data['symbol'];
		$life_time = $currency_data['lifetime'];

		$price = round( $price, $decimal );

		$wpdb->query(
			$wpdb->prepare("
				CALL wc_ezdefi_generate_amount_id(%s, %s, %d, %d, @amount_id)
			", $price, $symbol, $decimal, $life_time)
		);

		$result = $wpdb->get_row( "SELECT @amount_id", ARRAY_A );

		if( ! $result ) {
			return null;
		}

		$amount_id = floatval( $result['@amount_id'] );

		$acceptable_variation = $this->get_acceptable_variation();

		$variation_percent = $acceptable_variation / 100;

		$min = floatval( $price - ( $price * $variation_percent ) );
		$max = floatval( $price + ( $price * $variation_percent ) );

		if( ( $amount_id < $min ) || ( $amount_id > $max ) ) {
			return null;
		}

		return $amount_id;
	}

	/**
	 * Get acceptable variation option
	 *
	 * @return string
	 */
	public function get_acceptable_variation()
	{
		return $this->get_option( 'acceptable_variation' );
	}

	/**
	 * Get amount table name
	 *
	 * @return string
	 */
	public function get_amount_table_name()
	{
		global $wpdb;

		return $wpdb->prefix . 'woocommerce_ezdefi_amount';
	}

	public function delete_amount_id_exception($amount_id, $currency, $order_id)
	{
		global $wpdb;

		$table_name = $wpdb->prefix . 'woocommerce_ezdefi_exception';

		if( is_null( $order_id ) ) {
			return $wpdb->query( "DELETE FROM $table_name WHERE amount_id = $amount_id AND currency = '$currency' AND order_id IS NULL LIMIT 1" );
		}

		return $wpdb->query( "DELETE FROM $table_name WHERE amount_id = $amount_id AND currency = '$currency' AND order_id = $order_id" );
	}

	public function add_exception( $data )
	{
		global $wpdb;

		$keys = array();
		$values = array();

		foreach ( $data as $key => $value ) {
			$keys[] = "$key";
			$values[] = "'$value'";
		}

		$exception_table = $wpdb->prefix . 'woocommerce_ezdefi_exception';

		$query = "INSERT INTO $exception_table (" . implode( ',', $keys ) . ") VALUES (" . implode( ',', $values ) . ")";

		return $wpdb->query($query);
	}

	public function add_or_update_exception( $data )
	{
		global $wpdb;

		$keys = array();
		$values = array();

		foreach ( $data as $key => $value ) {
			$keys[] = "$key";
			$values[] = "'$value'";
		}

		$exception_table = $wpdb->prefix . 'woocommerce_ezdefi_exception';

		$query = "INSERT INTO $exception_table (" . implode( ',', $keys ) . ") VALUES (" . implode( ',', $values ) . ")";

		$currency = $data['currency'];
		$amount_id = $data['amount_id'];

		$query .= " ON DUPLICATE KEY UPDATE currency = '$currency', amount_id = '$amount_id'";

		return $wpdb->query($query);
	}

	public function get_exception( $params = array(), $offset = 0, $per_page = 15 )
	{
		global $wpdb;

		$exception_table = $wpdb->prefix . 'woocommerce_ezdefi_exception';

		$meta_table = $wpdb->prefix . 'postmeta';

		$default = array(
			'amount_id' => '',
			'currency' => '',
			'order_id' => '',
			'email' => '',
			'payment_method' => '',
			'status' => ''
		);

		$params = array_merge( $default, $params );

		$query = "SELECT SQL_CALC_FOUND_ROWS t1.*, t2.billing_email FROM $exception_table t1 LEFT JOIN ( SELECT post_id as order_id, meta_value as billing_email FROM $meta_table WHERE `meta_key` = '_billing_email' ) t2 ON t1.order_id = t2.order_id";

		$sql = array();

		foreach( $params as $column => $param ) {
			if( ! empty( $param ) && in_array( $column, array_keys( $default ) ) && $column != 'amount_id' ) {
				$sql[] = ( $column === 'email' ) ? " t2.billing_email = '$param' " : " t1.$column = '$param' ";
			}
		}

		if( ! empty( $sql ) ) {
			$query .= ' WHERE ' . implode( $sql, 'AND' );
		}

		if( ! empty( $params['amount_id'] ) ) {
			$amount_id = $params['amount_id'];
			if( ! empty( $sql ) ) {
				$query .= " AND";
			} else {
				$query .= " WHERE";
			}
			$query .= " amount_id RLIKE '^$amount_id'";
		}

		$query .= " ORDER BY id DESC LIMIT $offset, $per_page";

		$data = $wpdb->get_results( $query );

		$total = $wpdb->get_var( "SELECT FOUND_ROWS() as total;" );

		return array(
			'data' => $data,
			'total' => $total
		);
	}

	public function update_exception( $wheres = array(), $data = array() )
	{
		global $wpdb;

		$exception_table = $wpdb->prefix . 'woocommerce_ezdefi_exception';

		if( empty( $data ) || empty( $wheres ) ) {
			return;
		}

		$query = "UPDATE $exception_table SET";

		$comma = " ";

		foreach ( $data as $column => $value ) {
			$query .= $comma . $column . " = '" . $value . "'";
			$comma = ", ";
		}

		$conditions = array();

		foreach( $wheres as $column => $value ) {
			if( ! empty( $value ) ) {
				$type = gettype( $value );
				switch ($type) {
					case 'double' :
						$conditions[] = " $column = $value ";
						break;
					case 'integer' :
						$conditions[] = " $column = $value ";
						break;
					case 'string' :
						$conditions[] = " $column = '$value' ";
						break;
					case 'NULL' :
						$conditions[] = " $column IS NULL ";
						break;
				}
			}
		}

		if( ! empty( $conditions ) ) {
			$query .= ' WHERE ' . implode( $conditions, 'AND' );
		}

		return $wpdb->query( $query );
	}
}