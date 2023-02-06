<?php

namespace Drutiny\Audit\Filesystem;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;

/**
 * Large files
 */
class FsSize extends Audit
{

    public function configure():void
    {
        $this->addParameter(
            'max_size',
            static::PARAMETER_OPTIONAL,
            'The maximum size in MegaBytes a directory should be.',
            20
        );
        $this->addParameter(
            'path',
            static::PARAMETER_REQUIRED,
            'The path of the directory to check for size.'
        );
    }


    /**
     * @inheritdoc
     */
    public function audit(Sandbox $sandbox)
    {
        $path = $this->getParameter('path', '%files');
        $stat = $sandbox->drush(['format' => 'json'])->status();

        if (!isset($stat['%paths'])) {
            foreach ($stat as $key => $value) {
                $stat['%paths']['%'.$key] = $value;
            }
        }

        $path = strtr($path, $stat['%paths']);

        $size = trim($this->target->getService('exec')->run("du -d 0 -m $path | awk '{print $1}'"));

        $max_size = (int) $this->getParameter('max_size', 20);

        // Set the size in MB for rendering
        $this->set('size', $size);

        // Backwards compatibility. Older policies use the output variable.
        $this->set('output', $size);
        
        // Set the actual path.
        $this->set('path', $path);

        return $size < $max_size;
    }
}
