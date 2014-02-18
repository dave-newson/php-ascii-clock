<?php

/**
 * PHP ASCII Clock
 * A very basic, slightly crude ASCII Art clock, using PHP.
 *
 * @author Dave Newson <dave@davenewson.com>
 * @license MIT
 * @license http://opensource.org/licenses/MIT
 */

class ClockApp
{
    /**
     * @var     Renderer
     */
    protected $renderer;

    /**
     * @var     World   Contains renderable objects
     */
    protected $world;

    /**
     * Dispatch the application
     * Shows the Clock if 'tick' is requested
     * otherwise shows a simple AJAX requester, which requests ticks.
     */
    function dispatch()
    {
        if (isset($_REQUEST['tick']))
        {
            // Generate server time, if timestamp not given
            $time = (int) (isset($_REQUEST['time']) ? $_REQUEST['time'] : time() );

            $this->renderClock($time);
        }
        else
        {
            $this->renderPage();
        }
    }

    /**
     * RenderPage
     * Generates a static AJAX form which will continuously request new clock ticks
     */
    function renderPage()
    {
        // output
        $out = '<html>
<head>
    <meta charset="utf-8" />
    <style type="text/css">
        body
        {
            background: #000;
            color: #fff;
            font-family:"courier new";
            font-size:8px;
            font-weight: bold;
        }
    </style>
    <script type="text/javascript">
        window.onload = function()
        {
            var pauseClock = 0;
            var xhr = new XMLHttpRequest();
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4) {
                    if (xhr.status == 200) {
                        document.getElementById("clock").innerHTML = xhr.responseText;
                    }
                    else {
                        // Possible 403/500, delay time for 5 ticks.
                        pauseClock = 5;
                    }
                }
            };

            setInterval(function()
            {
                if (pauseClock > 0) {
                    pauseClock--;
                    return;
                }

                // Calculate current time, locally
                var date = new Date();
                var time = ((date.getTime()/1000) - (date.getTimezoneOffset()*60));

                // Request fresh tick
                xhr.open("GET", "?tick=1&time="+time, true);
                xhr.send();
            }, 900);
        };
    </script>
</head>
<body>
    <h1>
        PHP-based ASCII Art Clock
    </h1>
    <p> by Dave Newson (davenewson.com)</p>
    <p>Note: Ticks are erratic as each tick is requested via AJAX every 900ms.</p>

    <pre id="clock">Clock goes here</pre>
</body>
</html>';

        // render
        exit ($out);
    }

    /**
     * RenderClock
     * Initialises the clock itself
     * Initialises and calls renderer
     * @param int   $time       Time in seconds to display
     */
    function renderClock( $time )
    {
        // Init renderer
        $this->renderer = new AsciiRenderer();
        $this->renderer->setSize(60,60);

        // Init world
        $this->world = new World;

        // Create the clock, init, add to world
        $clock = new ClockActor();
        $clock->setTime( $time );
        $this->world->addChild($clock);

        // Add to render queue
        $this->renderer->render($this->world);

        exit($this->renderer->getBuffer());
    }
}


/**
 * Container
 * Abstract container for Actor objects
 */

abstract class Container
{
    /**
     * @var array   Child objects that can be rendered
     */
    protected $children = array();

    /**
     * Add a child to this Actor
     * @param Actor $child      Child Actor
     */
    public function addChild( Actor $child )
    {
        $this->children[] = $child;
    }

    /**
     * Fetch all children
     * @return array
     */
    public function getChildren()
    {
        return $this->children;
    }
}


/**
 * World
 * Implementation of container, to contain all Actors in our world.
 */
class World extends Container
{

}


/**
 * Actor
 * Renderable object
 */
abstract class Actor extends container
{
    // Position
    public $x = 0;
    public $y = 0;

    public abstract function render( $renderer );
}


/**
 * Clock Actor
 * Builds the clock for rendering
 */
class ClockActor extends Actor
{
    /**
     * @var float   Timestamp (seconds) for this tick
     */
    protected $time = 0;

    /**
     * @param $timestamp    float       Set the time for this clock
     */
    public function setTime( $timestamp )
    {
        $this->time = $timestamp;
    }

    /**
     * Render the clock
     */
    public function render( $renderer )
    {
        // Find middle of grid
        $size = $renderer->getSize();
        $midX = ceil($size[0]/2);
        $midY = ceil($size[1]/2);

        // Render clock face
        $radius = 22;
        $angle = 0;
        $angleStep = 0.01;

        // Draw the clock face (circle)
        while ($angle < 2*M_PI)
        {
            $x = $radius * cos($angle);
            $y = $radius * sin($angle);

            $renderer->translate($midX+$x,$midY+$y,true);
            $renderer->drawPixel(1);

            $angle += $angleStep;
        }

        // Establish the time (Hour, Minute, Second, Microsecond)
        $hour = date('h', $this->time);
        $minute = date('i', $this->time);
        $second = date('s', $this->time);

        // Second Hand
        $secondHand = new LineActor();
        $secondHand->setAngle( (360 / 60) * ($second) );
        $secondHand->setLength( 20 );
        $secondHand->setColour(0.25);
        $secondHand->x = $midX;
        $secondHand->y = $midY;

        // Minute hand
        $minuteHand = new LineActor();
        $minuteHand->setAngle( (360 / 60) * ($minute + ($second/60)) );
        $minuteHand->setLength( 15 );
        $minuteHand->setColour(0.5);
        $minuteHand->x = $midX;
        $minuteHand->y = $midY;

        // Hour hand
        $hourHand = new LineActor();
        $hourHand->setAngle( (360 / 12) * ($hour + ($minute/60))  );
        $hourHand->setLength( 10 );
        $hourHand->setColour(0.75);
        $hourHand->x = $midX;
        $hourHand->y = $midY;

        // Add as child of this clock, so they get rendered
        $this->addChild($hourHand);
        $this->addChild($minuteHand);
        $this->addChild($secondHand);
    }
}


/**
 * Line Actor
 * Renderable vector line
 */

class LineActor extends Actor
{
    /**
     * @var int     Angle of this line
     */
    protected $angle = 0;

    /**
     * @var int     Lengh of this line
     */
    protected $length = 0;

    /**
     * @var int     Colour of this line
     */
    protected $colour = 1;

    /**
     * Set the angle of this line
     * @param $angle    int     Angle of degrees (0-360)
     */
    public function setAngle( $angle )
    {
        $this->angle = $angle;
    }

    /**
     * Set the length of the line
     * @param $length   int     Length in pixels
     */
    public function setLength( $length )
    {
        $this->length = $length;
    }

    /**
     * Set colour of this line
     * @param $colour    float   Colour (greyscale)
     */
    public function setColour( $colour )
    {
        $this->colour = $colour;
    }

    /**
     * Render the line
     */
    public function render( $renderer )
    {
        // Set Position of line. Always draws from this origin
        $renderer->translate($this->x, $this->y, true);

        // Convert angle to normalised Vector
        $x = sin( $this->angle * M_PI / 180);
        $y = cos( $this->angle * M_PI / 180);

        // Give the line length
        for ($i=0;$i<$this->length;$i++)
        {
            $renderer->translate( $x, -$y );
            $renderer->drawPixel( $this->colour );
        }
    }
}


/**
 * AsciiRenderer
 * Render given instructions to an ASCII-based buffer
 */
class AsciiRenderer
{
    /**
     * Buffer container (x/y dimentioned array)
     * @var array
     */
    protected $buffer = array();

    /**
     * Pixels, in brightness from dark (0) to light (1).
     * @var string
     */
    protected $pixels = " .`-_':,;^=+/\"|)\\<>)iv%xclrs{*}I?!][1taeo7zjLunT#JCwfy325Fp6mqSghVd4EgXPGZbYkOA&8U$@KHDBWNMR0Q";

    /**
     * Set size (width and height) of buffer
     * @param   int $width      Width
     * @param   int $height     Height
     */
    public function setSize( $width, $height )
    {
        for ($x=0; $x<$width; $x++)
        {
            $this->buffer[$x] = array();

            for ($y=0; $y<$height; $y++)
            {
                $this->buffer[$x][$y] = 0;
            }
        }
    }


    /**
     * Get size of the grid
     * @return array    x and y dims
     */
    public function getSize()
    {
        $x = sizeof($this->buffer);
        $y = sizeof($this->buffer[0]);
        return array($x,$y);
    }

    /**
     * Render the given container and all it's children
     * @param Container $container
     */
    public function render( Container $container )
    {
        foreach ($container->getChildren() as $c)
        {
            $this->processActor($c);
        }
    }

    /**
     * Process and render a particular actor to the buffer
     * @param Actor $actor
     */
    public function processActor( Actor $actor )
    {
        $actor->render($this);

        // Process children if they exist
        $children = &$actor->getChildren();
        if (sizeof($children))
        {
            foreach ($children as &$c)
            {
                // Recursive actor processing
                $this->processActor($c);
            }
        }
    }

    /**
     * Set alpha of current pixel
     * @param   float   $a  Alpha of pixel
     */
    public function drawPixel($a)
    {
        // Only work on absolute pixels for now
        // TODO: Ascii anti-aliasing based on float-offset positions
        $x = round($this->xPos);
        $y = round($this->yPos);

        // Buffer write
        $this->buffer[$y][$x] = (float) $a;
    }

    /**
     * Move to pixel position
     * @param   int     $x  X pos
     * @param   int     $y  Y pos
     * @param   bool    $absolute  True to specify absolute coordinates
     */
    public function translate($x, $y, $absolute = false)
    {
        if ($absolute)
        {
            $this->xPos = 0;
            $this->yPos = 0;
        }

        $this->xPos += $x;
        $this->yPos += $y;
        
    }

    /**
     * Fetch buffer for output as string
     * @return string       ASCII art buffer
     */
    public function getBuffer()
    {
        // Condense 2d array to 1d (lines)
        foreach ($this->buffer as &$line)
        {
            foreach ($line as &$cell)
            {
                $cell = $this->redrawPixelToAscii($cell);
            }

            $output[] = implode('', $line);
        }

        // condense 1d array to string (block)
        $output = implode("\n", $output);

        // Output
        return $output;
    }

    /**
     * Quick function to redraw scalar pixel value to Ascii character.
     * @param $pixel    float       Single pixel value
     * @return string   string      Single character
     */
    public function redrawPixelToAscii( $pixel )
    {
        // Fetch pixel pallet
        $len = strlen($this->pixels)-1;
        $c = ceil($len*$pixel);

        // Select correct colour for pixel brightness
        $char = $this->pixels{$c};

        // Append twice, to fix ASCII aspect ratio
        // TODO: Implement real aspect ratio handling.
        return $char.$char;
    }

}


/**
 * Initialise application
 */

// Ensures everyone gets their local time, as passed by JavaScript.
date_default_timezone_set('UTC');

// Call the app
$clock = new ClockApp;
$clock->dispatch();