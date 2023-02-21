<?php

namespace Drutiny\Report\Format;

use Drutiny\AssessmentInterface;
use Drutiny\Profile;
use Drutiny\Report\FormatInterface;
use Drutiny\Attribute\AsFormat;


#[AsFormat(
  name: 'html',
  extension: 'html'
)]
class HTML extends TwigFormat
{
    public function setOptions(array $options = []):FormatInterface
    {
        if (!isset($options['content'])) {
            $options['content'] = $this->loadTwigTemplate('report/profile');
        }
        elseif (is_string($options['content'])) {
            $options['content'] = $this->twig->createTemplate($options['content']);
        }
        $options['template'] = $options['template'] ?? 'report/page.' . $this->getExtension() . '.twig';
        return parent::setOptions($options);
    }


    protected function prepareContent(Profile $profile, AssessmentInterface $assessment):array
    {
      // 2.x style no longer supported.
      if (is_array($this->options['content'])) {
        throw new \Exception("format.html.content in profile is using 2.x format. Not supported in 3.4 or later.");
      }

      $variables = ['profile' => $profile, 'assessment' => $assessment];
      $sections = [];
      $markdown = $this->twig->getRuntime('Twig\Extra\Markdown\MarkdownRuntime');

      // In 3.x we support Twig TemplateWrappers to be passed directly
      // to the report format.
      foreach ($this->options['content']->getBlockNames() as $block){
        $sections[] = $markdown->convert($this->options['content']->renderBlock($block, $variables));
      }
      return $sections;
    }
}
