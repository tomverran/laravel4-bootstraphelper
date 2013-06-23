<?php
/**
 * BootstrapBladeCompiler.php
 * @author Tom
 * @since 22/06/13
 */
namespace carefulcoder\bootstrapblade;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\Filesystem\Filesystem;

/**
 * Class BootstrapBladeCompiler
 * @package carefulcoder\bootstrapblade
 */
class BootstrapBladeCompiler extends BladeCompiler {

    /**
     * @var array Compiler functions to add to Blade.
     */
    protected $bootstrapCommands = array(
        'modal', 'head', 'nav', 'cta', 'hero', 'foot'
    );

    /**
     * @var string Symbol to prefix commands with.
     */
    protected $bootstrapSymbol = '%';

    /**
     * @var string Path of Bootstrap media relative to the public folder.
     */
    protected $bootstrapPath = 'bootstrap/';

    /**
     * Construct this BootstrapBladeCompiler
     * @param Filesystem $files Fileystem Library for loading views.
     * @param string $cachePath A path to the cache.
     */
    public function __construct(Filesystem $files, $cachePath)
    {
        parent::__construct($files, $cachePath);
        $this->compilers = array_merge(array('BootstrapRegex'), $this->compilers);
    }

    /**
     * I am not good at regular expressions so instead
     * over-engineered an approach based on reflection! Yay!
     * Slow as hell I imagine but the compiled output is cached by Laravel.
     */
    protected function compileBootstrapRegex($view)
    {
        //iterate through all of the available commands
        foreach ($this->bootstrapCommands as $command) {

            $matches = array();
            $methodName = 'compile' . ucfirst($command);

            //Do we have a method for this command?
            if (method_exists($this, $methodName)) {

                //find all instances of our command and the arguments provided with a simple regex.
                preg_match_all('/'.preg_quote($this->bootstrapSymbol.$command) . ' ?([^\n\r]*)/i', $view, $matches);

                //replace the matched commands with compiled output
                foreach (array_unique($matches[0]) as $index=>$match) {
                    $args = array_map('trim', explode(',', $matches[1][$index]));
                    $view = str_replace($matches[0], call_user_func_array(array($this, $methodName), $args), $view);
                }
            }
        }
        return $view;
    }

    /**
     * Replace %head [responsive] with links for styles
     * @param null $responsive Whether to include responsive styles.
     * @internal param string $view The view compiled so far.
     * @return mixed The compiled view.
     */
    protected function compileHead($responsive = null)
    {
        //always include the minified bootstrap CSS
        $ret = "{{ HTML::style('{$this->bootstrapPath}css/bootstrap.min.css') }}";

        //append responsive styles?
        if ($responsive) {
            $ret .= "{{ HTML::style('{$this->bootstrapPath}css/bootstrap-responsive.css') }}";
        }

        return $ret;
    }

    /**
     * Replace %foot [no-jquery] with Bootstrap JS and JQuery from the Google CDN unless no-jquery is set.
     * @param null $omitJquery Whether to not bother including JQuery. Either null or "no-jquery"
     * @return string
     */
    protected function compileFoot($omitJquery = null)
    {
        $ret = "{{ HTML::script('bootstrap/js/bootstrap.min.js') }}";
        if ($omitJquery != 'no-jquery') {
            $ret = "{{ HTML::script('//ajax.googleapis.com/ajax/libs/jquery/1.10.1/jquery.min.js') }}" . $ret;
        }
        return $ret;
    }

    /**
     * @param $id
     * @param $view
     * @return mixed
     */
    protected function compileModal($id, $view)
    {
        //e.g. register from forms/register.
        $parts = explode('/', $view);
        $viewNs = end($parts);

        return  '
        @include("'.$view.'")
        <div id="'.$id.'" class="modal hide fade">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                @yield("'.$viewNs.'-header")
            </div>
            <div class="modal-body">
                @yield("'.$viewNs.'-content")
            </div>
            <div class="modal-footer">
                <a href="#" class="btn">Close</a>
                @yield("'.$viewNs.'-footer")
            </div>
        </div>';
    }

    /**
     * Replace %nav name, [elements...] With a dark navbar
     * @param String $name Name of the app
     * @return string
     */
    protected function compileNav($name)
    {
        $args = func_get_args();
        array_shift($args);

        $nav = '<div class="navbar navbar-inverse navbar-fixed-top">
          <div class="navbar-inner">
            <div class="container">

              <!-- .btn-navbar is used as the toggle for collapsed navbar content -->
              <a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
              </a>

              <!-- Be sure to leave the brand out there if you want it shown -->
              <a class="brand" href="#">'.$name.'</a>

              <!-- Everything you want hidden at 940px or less, place within here -->
              <div class="nav-collapse collapse"><ul class="nav">';

                foreach ($args as $data) {
                    $parts = explode('->', $data);
                    if (count($parts) == 2) {
                        $nav .= '<li><a href="{{ URL::to("'.trim($parts[1]).'") }}">'.trim($parts[0]).'</a></li>';
                    }
                }

              return $nav . '</ul></div>
            </div>
          </div>
        </div>';
    }

    /**
     * Compile a Call to Action
     * @param String $text The text to show
     * @param String $buttonText Text on the button
     * @param String $buttonHref Contents of the button href field.
     * @return string The Call to Action in a well.
     */
    protected function compileCta($text, $buttonText, $buttonHref)
    {
        return '
        <div class="row">
            <div class="span12">
                <div class="well">
                    <div class="row-fluid">
                        <div class="span9">
                            <h4>'.$text.'</h4>
                        </div>
                        <div class="span3">
                            <a href="'.$buttonHref.'" role="button" class="btn btn-success btn-large btn-block" data-toggle="modal">
                                '.$buttonText.'&nbsp; <i class="icon-white icon-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
    }

    /**
     * Compile a "hero" element with the given H1 and H3.
     * @param string $text The big, heroic text.
     * @param string $subtext The smaller text.
     * @return string
     */
    protected function compileHero($text, $subtext)
    {
        return '
        <div class="hero-unit">
            <h1>'.$text.'</h1>
            <p>'.$subtext.'</p>
        </div>';
    }
}