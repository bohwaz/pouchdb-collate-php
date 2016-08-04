<?php

use \Bohwaz\PouchCollate\Collate;

error_reporting(-1);
ini_set('display_errors', true);
assert_options(ASSERT_ACTIVE, true);
assert_options(ASSERT_WARNING, false);
assert_options(ASSERT_CALLBACK, 'assert_fail');

require __DIR__ . '/../src/Collate.php';

function assert_fail($file, $line, $code, $desc = null)
{
	echo '[FAIL] :' . $line . ': ' . trim($desc) . "\n";
}

$fail = false;

try {
	Collate::parseIndexableString('not a serialized string');
}
catch (\Exception $e)
{
	$fail = $e->getMessage();
}

assert($fail === 'Not a serialized string: no NUL byte found (is this a serialized string?)', 'Invalid string');

// Number serialization
assert(
	'23256.70000000000000017764' == Collate::numToIndexableString(67),
	'Number serialization: (int) 67');
assert(
	'03249' == Collate::numToIndexableString(-1),
	'Number serialization: -1');
assert(
	'23331.47073619999999993802' == Collate::numToIndexableString(1470736200),
	'Number serialization: (int) 1470736200');

// Example serialization
$example = [67, true, 'McDuck', 'Scrooge'];

$s = Collate::toIndexableString($example);
$s = substr(json_encode($s), 1, -1);
assert(
	'5323256.70000000000000017764\u000021\u00004McDuck\u00004Scrooge\u0000\u0000' == $s
	);

// Example unserialization
$s = Collate::parseIndexableString(json_decode('"5323256.70000000000000017764\u000021\u00004McDuck\u00004Scrooge\u0000\u0000"'));
assert($example == $s, 'Unserialize: ' . print_r($s, true));

// Test data
if (!defined('PHP_INT_MIN'))
{
	define('PHP_INT_MIN', ~PHP_INT_MAX);
}

// Strings are from the javascript library output
$keys = [
	"1\u0000" => null,
	"20\u0000" => false,
	"21\u0000" => true,
	"31\u0000" => 0,
	"323241\u0000" => 1,
	"303249\u0000" => -1,
	"323249\u0000" => 9,
	"303241\u0000" => -9,
	"323251\u0000" => 10,
	"303239\u0000" => -10,
	"323231\u0000" => 0.1,
	"303259\u0000" => -0.1,
	"303269\u0000" => -0.01,
	"323261\u0000" => 100,
	"323262\u0000" => 200,
	"323252\u0000" => 20,
	"303238\u0000" => -20,
	"303228\u0000" => -200,
	"303237\u0000" => -30,
	// Very large numbers don't match correctly for now
	//"326321.79769313486231574473\u0000" => 1.7976931348623157e+308,
	//"320005\u0000" => 5e-324,
	//"300168.20230686513768425527\u0000" => -1.7976931348623157e+308,
	"4foo\u0000" => "foo",
	"4\u0000" => "",
	"4\u0001\u0001\u0000" => chr(0),
	"4\u0001\u0002\u0000" => chr(1),
	"4\u0002\u0002\u0000" => chr(2),
	"5323241\u0000\u0000" => [1],
	"64foo\u000021\u0000\u0000" => (object)["foo"=>true],
	"64foo\u00004bar\u00004baz\u00004quux\u00004foobaz\u000064bar\u00004bar\u00004baz\u00004baz\u00004quux\u000064foo\u00004bar\u0000\u0000\u0000\u0000" => (object)["foo"=>"bar","baz"=>"quux","foobaz"=>(object)["bar"=>"bar","baz"=>"baz","quux"=>(object)["foo"=>"bar"]]],
	"64foo\u000064bar\u000021\u0000\u0000\u0000" => (object)["foo"=>(object)["bar"=>true]],
	"564foo\u00004bar\u0000\u000064bar\u00004baz\u0000\u00006\u000054foo\u00004bar\u00004baz\u0000\u0000\u0000" => [(object)["foo"=>"bar"],(object)["bar"=>"baz"],(object)[],["foo","bar","baz"]],
	"55554foo\u0000\u0000\u00005\u0000554bar\u0000\u0000\u0000\u0000\u0000" => [[[["foo"]],[],[["bar"]]]],
	"303227\u0000" => -300,
	"303228\u0000" => -200,
	"303229\u0000" => -100,
	"303239\u0000" => -10,
	"303247.5\u0000" => -2.5,
	"303248\u0000" => -2,
	"303248.5\u0000" => -1.5,
	"303249\u0000" => -1,
	"303255\u0000" => -0.5,
	"303289\u0000" => -0.0001,
	"31\u0000" => 0,
	"323201\u0000" => 0.0001,
	"323231\u0000" => 0.1,
	"323235\u0000" => 0.5,
	"323241\u0000" => 1,
	"323241.5\u0000" => 1.5,
	"323242\u0000" => 2,
	"323243\u0000" => 3,
	"323251\u0000" => 10,
	"323251.5\u0000" => 15,
	"323261\u0000" => 100,
	"323262\u0000" => 200,
	"323263\u0000" => 300,
	"4\u0000" => "",
	"41\u0000" => "1",
	"410\u0000" => "10",
	"4100\u0000" => "100",
	"42\u0000" => "2",
	"420\u0000" => "20",
	"4[]\u0000" => "[]",
	"4\u00e9\u0000" => "é",
	"4foo\u0000" => "foo",
	"4mo\u0000" => "mo",
	"4moe\u0000" => "moe",
	"4moz\u0000" => "moz",
	"4mozilla\u0000" => "mozilla",
	"4mozilla with a super long string see how far it can go\u0000" => "mozilla with a super long string see how far it can go",
	"4mozzy\u0000" => "mozzy",
	"5\u0000" => [],
	"51\u0000\u0000" => [null],
	"51\u00001\u0000\u0000" => [null,null],
	"51\u00004foo\u0000\u0000" => [null,"foo"],
	"520\u0000\u0000" => [false],
	"520\u0000323261\u0000\u0000" => [false,100],
	"521\u0000\u0000" => [true],
	"521\u0000323261\u0000\u0000" => [true,100],
	"531\u0000\u0000" => [0],
	"531\u00001\u0000\u0000" => [0,null],
	"531\u0000323241\u0000\u0000" => [0,1],
	"531\u00004\u0000\u0000" => [0,""],
	"531\u00004foo\u0000\u0000" => [0,"foo"],
	"54\u00004\u0000\u0000" => ["",""],
	"54foo\u0000\u0000" => ["foo"],
	"54foo\u0000323241\u0000\u0000" => ["foo",1],
	"6\u0000" => (object)[],
	"640\u00001\u0000\u0000" => (object)["0"=>null],
	"640\u000020\u0000\u0000" => (object)["0"=>false],
	"640\u000021\u0000\u0000" => (object)["0"=>true],
	"640\u000031\u0000\u0000" => (object)["0"=>0],
	"640\u0000323241\u0000\u0000" => (object)["0"=>1],
	"640\u00004bar\u0000\u0000" => (object)["0"=>"bar"],
	"640\u00004foo\u0000\u0000" => (object)["0"=>"foo"],
	"640\u00004foo\u000041\u000020\u0000\u0000" => (object)["0"=>"foo","1"=>false],
	"640\u00004foo\u000041\u000021\u0000\u0000" => (object)["0"=>"foo","1"=>true],
	"640\u00004foo\u000041\u000031\u0000\u0000" => (object)["0"=>"foo","1"=>0],
	"640\u00004foo\u000041\u000040\u0000\u0000" => (object)["0"=>"foo","1"=>"0"],
	"640\u00004foo\u000041\u00004bar\u0000\u0000" => (object)["0"=>"foo","1"=>"bar"],
	"640\u00004quux\u0000\u0000" => (object)["0"=>"quux"],
	"641\u00004foo\u0000\u0000" => (object)["1"=>"foo"],
];

// Check if we can unserialize correctly
foreach ($keys as $key)
{
	$key_serialized = Collate::toIndexableString($key);
	assert(
		json_encode($key) == json_encode(Collate::parseIndexableString($key_serialized)),
		'Serialize/unserialize: (' . gettype($key). ') ' . print_r($key, true) . ' = ' . json_encode($key_serialized)
	);
}

// Check we get consistent results with the Javascript library
foreach ($keys as $value=>$key)
{
	$key_serialized = Collate::toIndexableString($key);
	assert(
		$value == substr(json_encode($key_serialized), 1, -1),
		'Serialize consistency with JS: ' . $value . ' = ' . print_r($key, true)
	);
}

// custom test
$s = Collate::toIndexableString((object)['schedule', 1470736200, 0, '1470700800-NZ2-D', 'flight']);
$s = substr(json_encode($s), 1, -1);

// String is from Pouch Collate  javascript
$test = '640\u00004schedule\u000041\u0000323331.47073619999999993802\u000042\u000031\u000043\u000041470700800-NZ2-D\u000044\u00004flight\u0000\u0000';
assert($test == $s, 'Custom real life data test');