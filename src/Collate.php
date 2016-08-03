<?php

namespace Bohwaz\PouchCollate;

use \Exception;

/**
 * This is a serialization utility class used by PouchDB for collation
 * Rewritten for PHP from javascript.
 * Original JS code is here: https://github.com/pouchdb/collate
 *
 * Serialization format is:
 * 5323256.70000000000000017764\u000021\u00004McDuck\u00004Scrooge\u0000\u0000
 *
 * Or in plain english:
 * (integer) (1) // data type: 1 = NULL, 2 = bool...
 * ... data
 * (string) NUL at the end of each data
 * 
 * @license BSD 3 clause
 * @author  BohwaZ <http://bohwaz.net/>
 * @copyright 2016 BohwaZ
 */
class Collate
{
	/**
	 * Minimal number magnitude
	 */
	const MIN_MAGNITUDE = -324; // verified by -Number.MIN_VALUE

	/**
	 * Number of digits used for the magnitude
	 */
	const MAGNITUDE_DIGITS = 3; // ditto

	/**
	 * String escaping, to make sure that the key does not contain any NUL byte
	 * @param  string $str String to escape
	 * @return string Escaped strng
	 */
	static protected function escapeString($str)
	{
		// Would be much easier in PHP7 with \u{0001} and so on, but for now we do what we can
		$table = [
			// replace(/\u0001\u0001/g, '\u0000')
			chr(0) => chr(1) . chr(1),
			// replace(/\u0001\u0002/g, '\u0001')
			chr(1) => chr(1) . chr(2),
			// replace(/\u0002\u0002/g, '\u0002')
			chr(2) => chr(2) . chr(2),
		];
		
		return strtr($str, $table);
	}

	/**
	 * Restore NUL bytes in original string
	 * @param  string $str Escaped string
	 * @return string      Unescaped string
	 */
	static protected function unescapeString($str)
	{
		// Would be much easier in PHP7 with \u{0001} and so on, but for now we do what we can
		$table = [
			// replace(/\u0001\u0001/g, '\u0000')
			chr(1) . chr(1) => chr(0),
			// replace(/\u0001\u0002/g, '\u0001')
			chr(1) . chr(2) => chr(1),
			// replace(/\u0002\u0002/g, '\u0002')
			chr(2) . chr(2) => chr(2),
		];
		
		return strtr($str, $table);
	}


	/**
	 * Custom number serialization
	 *
	 * Format is: SIGN . EXPONENT . MANTISSA
	 * SIGN = (int) 2 for positive, 0 for negative, 1 for zero (then no mantissa or exponent)
	 * EXPONENT = (for negative numbers negated) moved so that it's >= 0
	 * 
	 * @param  int|float $num Number, integers will be transformed to floats (yup, that's bad)
	 * @return string Encoded number
	 */
	static public function numToIndexableString($num)
	{
		// Zero is just stored as '1'
		if ($num === 0)
		{
			return '1';
		}

		$is_negative = $num < 0;
		$out = ($is_negative ? '0' : '2');

		// convert number to exponential format for easier and
		// more succinct string sorting
		
		// Maximum possible precision (20) to avoid compatibility problems with javascript implementation
		// PHP: sprintf('%e', 1470736200) => 1.470736e+9
		// JS: 1470736200.toExponential() => 1.4707362e+9
		// PHP fix: rtrim(sprintf('%.20e', 1470736200), '0') => 1.4707362e+9
		$exp = sprintf('%.20e', $num);
		
		// Remove ending zeros: useless
		$factor = rtrim(strtok($exp, 'e+'), '0');

		// get the magnitude
		$magnitude = (int) strtok('e+');

		$exponent = floor(log($num, 2)) + 1;

		$magForComparison = ($is_negative ? - $magnitude : $magnitude) - self::MIN_MAGNITUDE;
		$out .= str_pad((string) $magForComparison, self::MAGNITUDE_DIGITS, '0', STR_PAD_LEFT);

		// then sort by the factor
		$factor = abs((float) $factor); // [1..10)

		if ($is_negative)
		{
			// for negative reverse ordering
			$factor = 10 - $factor;
		}

		$factorStr = rtrim(number_format($factor, 20, '.', ''), '0.');

		return $out . $factorStr;
	}

	/**
	 * Serializes the given key to a string that would be appropriate
	 * for lexical sorting, e.g. within a database, where the
	 * sorting is the same given by the collate() function.
	 * @param  mixed $key  Input key, any type except resource
	 * @return string Serialized string
	 */
	static public function toIndexableString($key)
	{
		switch (gettype($key))
		{
			case 'NULL':
				return 1 . chr(0);
			case 'boolean':
				return 2 . self::normalizeKey($key) . chr(0);
			case 'integer':
			case 'double':
				if (is_nan($key) || $key === INF || $key === -INF)
				{
					// Considered as NULL
					return 1 . chr(0);
				}
				return 3 . self::normalizeKey($key) . chr(0);
			case 'string':
				return 4 . self::normalizeKey($key) . chr(0);
			case 'array':
			case 'object':
				$out = is_object($key) ? 6 : 5;

				$key = self::normalizeKey($key);
				
				foreach ($key as $v)
				{
					$out .= self::toIndexableString($v);
				}

				$out .= chr(0);
				return $out;
			default:
				throw new \Exception('Invalid variable type: ' . gettype($key));
		}
	}

	/**
	 * Normalizes a key for serialization
	 * @param  mixed  $key
	 * @return string
	 */
	static public function normalizeKey($key)
	{
		switch (gettype($key))
		{
			case 'string':
				return self::escapeString($key);
			case 'boolean':
				return $key ? '1' : '0';
			case 'integer':
			case 'double':
				return self::numToIndexableString($key);
			case 'object':
				$arr = [];

				foreach ($key as $k=>$v)
				{
					// In JS object keys are always strings, so we do the same here
					$arr[] = is_object($key) ? (string) $k : $k;
					$arr[] = $v;
				}

				return $arr;
			case 'array':
				// nothing to do
				return $key;
			default:
				throw new \Exception('Invalid variable type for normalizing: ' . gettype($key));
		}
	}

	/**
	 * Parse a serialized number into an integer or a float
	 * @param  string $str Serialized number
	 * @param  integer &$i Position in the serialized string (internal user)
	 * @return integer|float
	 */
	static public function parseNumber($str, &$i = 0)
	{
		// Zero
		if ($str[$i] === '1')
		{
			$i++;
			return 0;
		}

		// 0 = negative, 2 = positive
		$is_negative = ($str[$i++] === '0') ? true : false;

		$magnitude = (int) substr($str, $i, self::MAGNITUDE_DIGITS) + self::MIN_MAGNITUDE;
		$i += self::MAGNITUDE_DIGITS;

		if ($is_negative)
		{
			$magnitude = -$magnitude;
		}

		// Length of number (until next NUL byte)
		$length = strpos($str, chr(0), $i) - $i;
		$number = substr($str, $i, $length);
		$i += $length;
		
		// Extract integer and decimals
		$int = strtok($number, '.');
		$decimals = strtok('.');

		// Gets the number back to integer or float
		$number = !$decimals ? (int) $number : (float) $number;

		if ($is_negative)
		{
			$number -= 10;
		}

		if ($magnitude !== 0)
		{
			$number = sprintf('%f', $number . 'e' . $magnitude);

			// Determine if number is integer or float
			$int = strtok($number, '.');
			$decimals = strtok('.');

			// No decimals (or 0): integer
			if (!$decimals)
			{
				$number = (int) $number;
			}
			else
			{
				$number = (float) $number;
			}
		}

		return $number;
	}

	/**
	 * Unserialize an indexable string to get the original values back
	 * @example 5323256.70000000000000017764\u000021\u00004McDuck\u00004Scrooge\u0000\u0000
	 * @param  string  $str Serialized string
	 * @param  integer &$i  Current position in the string (internal use)
	 * @return mixed        Value, can be null, bool, integer, float, string, array or object
	 */
	static public function parseIndexableString($str, &$i = 0)
	{
		$stack = [];

		while ($i < strlen($str))
		{
			// closing NUL byte (end of array, end of string, end of object)
			// stop here and return the stack
			if (ord($str[$i]) === 0)
			{
				return $stack;
			}

			// Switch according to value type
			switch ((int) $str[$i++])
			{
				// NULL
				case 1:
					$value = null;
					break;
				// Boolean
				case 2:
					$value = (bool) $str[$i++];
					break;
				// Number
				case 3:
					$value = self::parseNumber($str, $i);
					break;
				// String
				case 4:
					// Find next NUL byte, everything between now and NUL is the string
					$length = strpos($str, chr(0), $i) - $i;
					$value = substr($str, $i, $length);
					$value = self::unescapeString($value);
					$i += $length;
					break;
				// Array
				case 5:
					// Note: associative arrays are not possible
					// (recursive call)
					$value = self::parseIndexableString($str, $i);
					break;
				// Object
				case 6:
					// Get result as array
					// (recursive call)
					$array = self::parseIndexableString($str, $i);

					$object = [];
					$j = 0;
					$obj_key = null;

					// Transform into an associative array
					foreach ($array as $value)
					{
						// One element out of two is the key, the other is the value
						if ($j++ %2  == 0)
						{
							$obj_key = $value;
						}
						else
						{
							$object[$obj_key] = $value;
						}
					}

					// Convert to object
					$value = (object) $object;
					break;
				default:
					$i--;
					throw new \Exception('Bad collationIndex or unexpectedly reached end of input at position ' . $i . ': ' . json_encode(substr($str, $i)));
			}

			$stack[] = $value;
			$i++;
		}

		// Only one element: this is not an array or object
		if (count($stack) === 1)
		{
			return array_pop($stack);
		}

		return $stack;
	}
}
