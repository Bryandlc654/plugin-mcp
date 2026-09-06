<?php

namespace MCPConnect;

defined( 'ABSPATH' ) || exit;

/**
 * Minimal JSON Schema (draft-07 subset) validator used for tool inputs.
 * Supports: type, properties, required, additionalProperties, enum, items,
 * minLength/maxLength, minimum/maximum, pattern, default, description.
 */
final class Schema_Validator {

	const TYPES = array( 'object', 'array', 'string', 'integer', 'number', 'boolean', 'null' );

	public function validate( &$value, array $schema, $path = '' ) {
		if ( empty( $schema ) ) {
			return true;
		}

		$type = isset( $schema['type'] ) ? $schema['type'] : null;
		if ( $type && in_array( $type, self::TYPES, true ) ) {
			$check = $this->check_type( $value, $type );
			if ( ! $check ) {
				return new_validation_error( $this->err( $path, 'invalid_type', sprintf( 'Expected type %s.', $type ) ) );
			}
		}

		if ( is_array( $value ) && $type && ( 'object' === $type || ! empty( $schema['properties'] ) ) ) {
			$result = $this->validate_object( $value, $schema, $path );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( is_array( $value ) && 'array' === $type && isset( $schema['items'] ) ) {
			foreach ( $value as $i => $item ) {
				$result = $this->validate( $item, $schema['items'], $path . '[' . $i . ']' );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}
		}

		if ( is_string( $value ) ) {
			if ( isset( $schema['minLength'] ) && function_exists( 'mb_strlen' ) && mb_strlen( $value ) < $schema['minLength'] ) {
				return new_validation_error( $this->err( $path, 'min_length', sprintf( 'Must be at least %d characters.', $schema['minLength'] ) ) );
			}
			if ( isset( $schema['maxLength'] ) && function_exists( 'mb_strlen' ) && mb_strlen( $value ) > $schema['maxLength'] ) {
				return new_validation_error( $this->err( $path, 'max_length', sprintf( 'Must be at most %d characters.', $schema['maxLength'] ) ) );
			}
			if ( isset( $schema['pattern'] ) && ! preg_match( '/' . $schema['pattern'] . '/', $value ) ) {
				return new_validation_error( $this->err( $path, 'pattern', 'Value does not match the required pattern.' ) );
			}
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			if ( isset( $schema['minimum'] ) && $value < $schema['minimum'] ) {
				return new_validation_error( $this->err( $path, 'minimum', sprintf( 'Must be at least %s.', $schema['minimum'] ) ) );
			}
			if ( isset( $schema['maximum'] ) && $value > $schema['maximum'] ) {
				return new_validation_error( $this->err( $path, 'maximum', sprintf( 'Must be at most %s.', $schema['maximum'] ) ) );
			}
		}

		if ( isset( $schema['enum'] ) && is_array( $schema['enum'] ) && ! in_array( $value, $schema['enum'], true ) ) {
			return new_validation_error( $this->err( $path, 'invalid_enum', sprintf( 'Allowed values: %s.', implode( ', ', $schema['enum'] ) ) ) );
		}

		return true;
	}

	private function validate_object( array &$object, array $schema, $path ) {
		$definitions = isset( $schema['properties'] ) ? $schema['properties'] : array();
		$required    = isset( $schema['required'] ) && is_array( $schema['required'] ) ? $schema['required'] : array();
		$strict      = isset( $schema['additionalProperties'] ) && false === $schema['additionalProperties'];

		foreach ( $required as $prop ) {
			if ( ! array_key_exists( $prop, $object ) ) {
				return new_validation_error( $this->err( $path, 'missing_required', sprintf( 'Missing required property: %s.', $prop ) ) );
			}
		}

		foreach ( $object as $prop => &$val ) {
			if ( $strict && ! isset( $definitions[ $prop ] ) ) {
				return new_validation_error( $this->err( $path, 'additional_properties', sprintf( 'Unexpected property: %s.', $prop ) ) );
			}
			$child = $path ? $path . '.' . $prop : $prop;
			if ( isset( $definitions[ $prop ] ) ) {
				$result = $this->validate( $val, $definitions[ $prop ], $child );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}
		}

		return true;
	}

	private function check_type( $value, $type ) {
		switch ( $type ) {
			case 'string':
				return is_string( $value );
			case 'integer':
				return is_int( $value );
			case 'number':
				return is_int( $value ) || is_float( $value );
			case 'boolean':
				return is_bool( $value );
			case 'array':
				return is_array( $value );
			case 'object':
				return is_array( $value );
			case 'null':
				return null === $value;
		}
		return true;
	}

	private function err( $path, $code, $message ) {
		return array(
			'path'    => $path,
			'code'    => $code,
			'message' => $message,
		);
	}
}

if ( ! function_exists( 'MCPConnect\new_validation_error' ) ) {
	function new_validation_error( array $error ) {
		return new \WP_Error( $error['code'], $error['message'], array( 'path' => $error['path'] ) );
	}
}