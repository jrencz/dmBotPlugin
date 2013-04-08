<?php
/**
 * dmBotTask runs dmBot in cli
 *
 * @package     dmBotPlugin
 * @subpackage  task
 * @author      Jarek Rencz <jrencz@polibuda.info>
 */
class dmBotTask extends dmContextTask
{
  public function configure() {
    $this->namespace        = 'project'; 
    $this->name             = 'bot';
    $this->briefDescription = 'Runs dmBot to check if pages load properly and preload cache';
    $this->addArguments(array(
      new sfCommandArgument('site-url', sfCommandArgument::REQUIRED, 'Url of site to browse'),
    ));
    $this->addOptions(array( 
      new sfCommandOption('application',   null, sfCommandOption::PARAMETER_REQUIRED, 'The application name', 'admin'), 
      new sfCommandOption('env',           null, sfCommandOption::PARAMETER_REQUIRED, 'The environment',      'prod'), 
      new sfCommandOption('limit',         'l',  sfCommandOption::PARAMETER_OPTIONAL, 'Limit'), 
      new sfCommandOption('only-active',   'a',  sfCommandOption::PARAMETER_OPTIONAL, 'Browse only active pages'), 
      new sfCommandOption('slug-pattern',  's',  sfCommandOption::PARAMETER_OPTIONAL, 'Browse only pages matching given slug'), 
      new sfCommandOption('title-pattern', 'k',  sfCommandOption::PARAMETER_OPTIONAL, 'Browse only pages matching given title'), 
      new sfCommandOption('name-pattern',  'n',  sfCommandOption::PARAMETER_OPTIONAL, 'Browse only pages matching given name'), 
      new sfCommandOption('verbose',       'v',  sfCommandOption::PARAMETER_NONE,     'Complete output'), 
    ));
  }
  
  public function execute($arguments = array(), $options = array()) 
  {
    
    $this->timerStart("bot");
    
    $this->withDatabase();
    
    $botoptions = array();
    
    if (!is_null($options['limit']))         $botoptions['limit']         = $options['limit'];
    if (!is_null($options['only-active']))   $botoptions['only_active']   = $options['only-active'];
    if (!is_null($options['slug-pattern']))  $botoptions['slug_pattern']  = $options['slug-pattern'];
    if (!is_null($options['name-pattern']))  $botoptions['name_pattern']  = $options['name-pattern'];
    if (!is_null($options['title-pattern'])) $botoptions['title_pattern'] = $options['title-pattern'];
    
    $this->bot = $this
      ->getContext()
      ->getServiceContainer()
      ->setParameter('dm_bot.options', $botoptions)
      ->getService('dm_bot')
      ->setBaseUrl($arguments['site-url'])
      ->init();
    
    $this->logSection('bot', $this->bot->getNbPages() . " pages to browse.", null, "COMMENT");
    
    foreach($this->bot->getPages() as $index => $page)
    {
      $url = $this->bot->getPageUrl($page);
      $statusCode = $this->bot->getPageStatusCode($page);
      
      if ($options['verbose']) $this->logSection('bot', 'will be browsing ' . $url, null, "INFO");
      $this->browse($url, $statusCode, $options);
    }
                                           
    $this->logTimersTotal();
    
  }
  
  private function browse($url, $expectedStatus = 200, $options = array()) 
  {
    $browser = $this->getContext()->getServiceContainer()->getService('web_browser');

    $browsingTime = $this->timerStart('browse-' . $url);
    
    $browser->get($url);

    if ($browser->getResponseCode() == $expectedStatus) 
    {
      if ($options['verbose']) $this->logSection('bot', 'got expected status code of ' . $browser->getResponseCode() . ' after ' . round($browsingTime->getElapsedTime()*1000, 2) . "ms", null, "COMMENT");
    }
    else 
    {
      $this->logSection('bot', $url . ' got unexpected code of ' . $browser->getResponseCode() . " (expected code was " . $expectedStatus . ")", null, "ERROR");
    }
  }
  
}
