<?php

use cebe\openapi\json\JsonReference;

class JsonPointerTest extends \PHPUnit\Framework\TestCase
{
    public function encodeDecodeData()
    {
        return [
            ['~0', '~'],
            ['~1', '/'],
            ['something', 'something'],
            ['~01', '~1'],
            ['~1~0', '/~'],
            ['~0~1', '~/'],
            ['~0~0', '~~'],
            ['~1~1', '//'],
            ['some~1path~1', 'some/path/'],
            ['1some0~11path0~1', '1some0/1path0/'],
            ['1some0~11path~00', '1some0/1path~0'],
        ];
    }

    /**
     * @dataProvider encodeDecodeData
     */
    public function testEncode($encoded, $decoded)
    {
        $this->assertEquals($encoded, \cebe\openapi\json\JsonPointer::encode($decoded));
    }

    /**
     * @dataProvider encodeDecodeData
     */
    public function testDecode($encoded, $decoded)
    {
        $this->assertEquals($decoded, \cebe\openapi\json\JsonPointer::decode($encoded));
    }

    /**
     * @link https://tools.ietf.org/html/rfc6901#section-5
     */
    public function rfcJsonDocument()
    {
        return <<<JSON
{
      "foo": ["bar", "baz"],
      "": 0,
      "a/b": 1,
      "c%d": 2,
      "e^f": 3,
      "g|h": 4,
      "i\\\\j": 5,
      "k\"l": 6,
      " ": 7,
      "m~n": 8
}
JSON;

    }

    /**
     * @link https://tools.ietf.org/html/rfc6901#section-5
     */
    public function rfcExamples()
    {
        $return = [
            [""      , "#"      , json_decode($this->rfcJsonDocument())],
            ["/foo"  , "#/foo"  , ["bar", "baz"]],
            ["/foo/0", "#/foo/0", "bar"],
            ["/"     , "#/"     , 0],
            ["/a~1b" , "#/a~1b" , 1],
            ["/c%d"  , "#/c%25d", 2],
            ["/e^f"  , "#/e%5Ef", 3],
            ["/g|h"  , "#/g%7Ch", 4],
            ["/i\\j" , "#/i%5Cj", 5],
            ["/k\"l" , "#/k%22l", 6],
            ["/ "    , "#/%20"  , 7],
            ["/m~0n" , "#/m~0n" , 8],
        ];
        foreach ($return as $example) {
            $example[3] = $this->rfcJsonDocument();
            yield $example;
        }
    }

    public function allExamples()
    {
        yield from $this->rfcExamples();

        yield ["/a#b" , "#/a%23b" , 16, '{"a#b": 16}'];
    }

    /**
     * @dataProvider allExamples
     */
    public function testUriEncoding($jsonPointer, $uriJsonPointer, $expectedEvaluation)
    {
        $pointer = new \cebe\openapi\json\JsonPointer($jsonPointer);
        $this->assertSame($jsonPointer, $pointer->getPointer());
        $this->assertSame($uriJsonPointer, JsonReference::createFromUri('', $pointer)->getReference());

        $reference = JsonReference::createFromReference($uriJsonPointer);
        $this->assertSame($jsonPointer, $reference->getJsonPointer()->getPointer());
        $this->assertSame('', $reference->getDocumentUri());
        $this->assertSame($uriJsonPointer, $reference->getReference());

        $reference = JsonReference::createFromReference("somefile.json$uriJsonPointer");
        $this->assertSame($jsonPointer, $reference->getJsonPointer()->getPointer());
        $this->assertSame("somefile.json", $reference->getDocumentUri());
        $this->assertSame("somefile.json$uriJsonPointer", $reference->getReference());
    }

    /**
     * @dataProvider rfcExamples
     */
    public function testEvaluation($jsonPointer, $uriJsonPointer, $expectedEvaluation)
    {
        $document = json_decode($this->rfcJsonDocument());
        $pointer = new \cebe\openapi\json\JsonPointer($jsonPointer);
        $this->assertEquals($expectedEvaluation, $pointer->evaluate($document));

        $document = json_decode($this->rfcJsonDocument());
        $reference = JsonReference::createFromReference($uriJsonPointer);
        $this->assertEquals($expectedEvaluation, $reference->getJsonPointer()->evaluate($document));
    }

    public function testEvaluationCases()
    {
        $document = (object) [
            "" => (object) [
                "" => 42
            ]
        ];
        $pointer = new \cebe\openapi\json\JsonPointer('//');
        $this->assertSame(42, $pointer->evaluate($document));

        $document = [
            "1" => null,
        ];
        $pointer = new \cebe\openapi\json\JsonPointer('/1');
        $this->assertNull($pointer->evaluate($document));

        $document = (object) [
            "k" => null,
        ];
        $pointer = new \cebe\openapi\json\JsonPointer('/k');
        $this->assertNull($pointer->evaluate($document));
    }


}
