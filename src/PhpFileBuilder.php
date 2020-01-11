<?php

namespace PKeidel\DBtoLaravel;

class PhpFileBuilder {

	public $namespace = NULL;
	public $classname = NULL;
	public $extends = NULL;
	public $implements = [];
	public $imports = [];
	public $use = [];
	public $doc = [];
	public $vars = [];
	public $functions = [];

	public function __construct($classname, $namespace = NULL) {
		$this->namespace = $namespace;
		$this->classname = $classname;
	}

	public function __toString() {
		$content = "<?php\n";

		if($this->namespace)
			$content .= "\nnamespace $this->namespace;\n\n";

		sort($this->imports);
		sort($this->use);

		if(count($this->imports))
			foreach($this->imports as $i)
				$content .= "use $i;\n";

		if(count($this->doc)) {
			$content .= "\n/**\n";
			foreach($this->doc as $d)
				$content .= rtrim(" * $d")."\n";
			$content .= "*/";
		}

		$content .= "\nclass $this->classname ";

		if($this->extends)
			$content .= "extends $this->extends ";

		if(count($this->implements))
			$content .= "implements ".implode(", ", $this->implements)." ";

		$content .= "{\n";

		if(count($this->use))
			foreach($this->use as $u)
				$content .= "    use $u;\n\n";

		if(count($this->vars))
			foreach($this->vars as $v)
				$content .= "    $v;\n";

        $content .= "\n";
        if(count($this->functions)) {
            foreach($this->functions as $fn) {
                $fn['visibility'] = $fn['visibility'] ?? 'public';
                $doc = "";
                foreach($fn['doc'] ?? [] as $c)
                    $doc .= "     * $c\n";
                if(strlen($doc) > 0) {
                    $doc = substr($doc, 0, -1);
                    $content .= "    /**\n$doc\n     */\n";
                }
                if(!empty($fn['comment']))
                    $content .= "    // {$fn['comment']}\n";
                $content .= "    {$fn['visibility']} function {$fn['name']}() {\n        {$fn['body']}\n    }\n\n";
            }
        }

        $content = substr($content, 0, -1)."}\n";

		return $content;
	}
}