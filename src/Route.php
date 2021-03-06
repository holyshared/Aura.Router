<?php
/**
 *
 * This file is part of the Aura for PHP.
 *
 * @package Aura.Router
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */
namespace Aura\Router;

use ArrayObject;
use Closure;

/**
 *
 * Represents an individual route with a name, path, params, values, etc.
 *
 * In general, you should never need to instantiate a Route directly. Use the
 * RouteFactory instead, or the Router.
 *
 * @package Aura.Router
 *
 * @property-read string $name The route name.
 *
 * @property-read string $path The route path.
 *
 * @property-read array $values Default values for params.
 *
 * @property-read array $params The matched params.
 *
 * @property-read Regex $regex The regular expression for the route.
 *
 * @property-read array $tokens The regular expression for the route.
 *
 * @property-read ArrayObject $matches All params found during `isMatch()`.
 *
 * @property-read array $debug Debugging messages.
 *
 * @property-read callable $generate A callable for generating a link.
 *
 * @property-read string $wildcard The name of the wildcard param.
 *
 */
class Route extends AbstractSpec
{
    /**
     *
     * The route failed to match at isRoutableMatch().
     *
     * @const string
     *
     */
    const FAILED_ROUTABLE = 'FAILED_ROUTABLE';

    /**
     *
     * The route failed to match at isSecureMatch().
     *
     * @const string
     *
     */
    const FAILED_SECURE = 'FAILED_SECURE';

    /**
     *
     * The route failed to match at isRegexMatch().
     *
     * @const string
     *
     */
    const FAILED_REGEX = 'FAILED_REGEX';

    /**
     *
     * The route failed to match at isMethodMatch().
     *
     * @const string
     *
     */
    const FAILED_METHOD = 'FAILED_METHOD';

    /**
     *
     * The route failed to match at isAcceptMatch().
     *
     * @const string
     *
     */
    const FAILED_ACCEPT = 'FAILED_ACCEPT';

    /**
     *
     * The route failed to match at isServerMatch().
     *
     * @const string
     *
     */
    const FAILED_SERVER = 'FAILED_SERVER';

    /**
     *
     * The route failed to match at isCustomMatch().
     *
     * @const string
     *
     */
    const FAILED_CUSTOM = 'FAILED_CUSTOM';

    /**
     *
     * The name for this Route.
     *
     * @var string
     *
     */
    protected $name;

    /**
     *
     * The path for this Route with param tokens.
     *
     * @var string
     *
     */
    protected $path;

    /**
     *
     * Matched param values.
     *
     * @var array
     *
     */
    protected $params = array();

    /**
     *
     * The `$path` property converted to a regular expression, using the
     * `$tokens` subpatterns.
     *
     * @var Regex
     *
     */
    protected $regex;

    /**
     *
     * All params found during the `isMatch()` process, both from the path
     * tokens and from matched server values.
     *
     * @var ArrayObject
     *
     * @see isMatch()
     *
     */
    protected $matches;

    /**
     *
     * Debugging information about why the route did not match.
     *
     * @var array
     *
     */
    protected $debug;

    /**
     *
     * The matching score for this route (+1 for each is*Match() that passes).
     *
     * @var int
     *
     */
    protected $score = 0;

    /**
     *
     * The failure code, if any, during matching.
     *
     * @var string
     *
     */
    protected $failed = null;

    /**
     *
     * Constructor.
     *
     * @param Regex $regex A regular expression support object.
     *
     * @param string $path The path for this Route with param token
     * placeholders.
     *
     * @param string $name The name for this route.
     *
     */
    public function __construct(Regex $regex, $path, $name = null)
    {
        $this->regex = $regex;
        $this->path = $path;
        $this->name = $name;
    }

    /**
     *
     * Magic read-only for all properties and spec keys.
     *
     * @param string $key The property to read from.
     *
     * @return mixed
     *
     */
    public function __get($key)
    {
        return $this->$key;
    }

    /**
     *
     * Magic isset() for all properties.
     *
     * @param string $key The property to check if isset().
     *
     * @return bool
     *
     */
    public function __isset($key)
    {
        return isset($this->$key);
    }

    /**
     *
     * Checks if a given path and server values are a match for this
     * Route.
     *
     * @param string $path The path to check against this Route.
     *
     * @param array $server A copy of $_SERVER so that this Route can check
     * against the server values.
     *
     * @return bool
     *
     */
    public function isMatch($path, array $server)
    {
        $this->debug = array();
        $this->params = array();
        $this->score = 0;
        $this->failed = null;
        if ($this->isFullMatch($path, $server)) {
            $this->setParams();
            return true;
        }
        return false;
    }

    /**
     *
     * @param string $path The path to check against this route
     *
     * @param array $server A copy of $_SERVER so that this Route can check
     * against the server values.
     *
     * @return bool
     *
     */
    protected function isFullMatch($path, array $server)
    {
        return $this->isRoutableMatch()
            && $this->isSecureMatch($server)
            && $this->isRegexMatch($path)
            && $this->isMethodMatch($server)
            && $this->isAcceptMatch($server)
            && $this->isServerMatch($server)
            && $this->isCustomMatch($server);
    }

    /**
     *
     * Internal function to add a score for a route
     *
     * @return bool
     *
     */
    protected function pass()
    {
        $this->score ++;
        return true;
    }

    /**
     *
     * @param string $failed The reason of failure
     *
     * @param string $append
     *
     * @return bool
     *
     */
    protected function fail($failed, $append = null)
    {
        $this->debug[] = $failed . $append;
        $this->failed = $failed;
        return false;
    }

    /**
     *
     * Check whether a failure happened due to accept header
     *
     * @return bool
     *
     */
    public function failedAccept()
    {
        return $this->failed == self::FAILED_ACCEPT;
    }

    /**
     *
     * Check whether a failure happened due to http method
     *
     * @return bool
     *
     */
    public function failedMethod()
    {
        return $this->failed == self::FAILED_METHOD;
    }

    /**
     *
     * Check whether a failure happened due to route not match
     *
     * @return bool
     *
     */
    protected function isRoutableMatch()
    {
        if ($this->routable) {
            return $this->pass();
        }

        return $this->fail(self::FAILED_ROUTABLE);
    }

    /**
     *
     * Checks that the Route `$secure` matches the corresponding server values.
     *
     * @param array $server A copy of $_SERVER.
     *
     * @return bool True on a match, false if not.
     *
     */
    protected function isSecureMatch($server)
    {
        if ($this->secure === null) {
            return $this->pass();
        }

        if ($this->secure != $this->serverIsSecure($server)) {
            return $this->fail(self::FAILED_SECURE);
        }

        return $this->pass();
    }

    /**
     *
     * Check whether the server is in secure mode
     *
     * @param array $server
     *
     * @return bool
     *
     */
    protected function serverIsSecure($server)
    {
        return (isset($server['HTTPS']) && $server['HTTPS'] == 'on')
            || (isset($server['SERVER_PORT']) && $server['SERVER_PORT'] == 443);
    }

    /**
     *
     * Checks that the path matches the Route regex.
     *
     * @param string $path The path to match against.
     *
     * @return bool True on a match, false if not.
     *
     */
    protected function isRegexMatch($path)
    {
        $regex = clone $this->regex;
        $match = $regex->match($this, $path);
        if (! $match) {
            return $this->fail(self::FAILED_REGEX);
        }
        $this->matches = new ArrayObject($regex->getMatches());
        return $this->pass();
    }


    /**
     *
     * Is the requested method matching
     *
     * @param array $server
     *
     * @return bool
     *
     */
    protected function isMethodMatch($server)
    {
        if (! $this->method) {
            return $this->pass();
        }

        if (in_array($server['REQUEST_METHOD'], $this->method)) {
            return $this->pass();
        }

        return $this->fail(self::FAILED_METHOD);
    }

    /**
     *
     * Is the accepted method matching
     *
     * @param array $server
     *
     * @return bool
     *
     */
    protected function isAcceptMatch($server)
    {
        if (! $this->accept || ! isset($server['HTTP_ACCEPT'])) {
            return $this->pass();
        }

        $header = str_replace(' ', '', $server['HTTP_ACCEPT']);

        if ($this->isAcceptMatchHeader('*/*', $header)) {
            return $this->pass();
        }

        foreach ($this->accept as $type) {
            if ($this->isAcceptMatchHeader($type, $header)) {
                return $this->pass();
            }
        }

        return $this->fail(self::FAILED_ACCEPT);
    }

    /**
     *
     * Is the accept method matching
     *
     * @param string $type
     *
     * @param string $header
     *
     * @return bool
     *
     */
    protected function isAcceptMatchHeader($type, $header)
    {
        list($type, $subtype) = explode('/', $type);
        $type = preg_quote($type);
        $subtype = preg_quote($subtype);
        $regex = "#$type/($subtype|\*)(;q=(\d\.\d))?#";
        $found = preg_match($regex, $header, $matches);
        if (! $found) {
            return false;
        }
        return isset($matches[3]) && $matches[3] !== '0.0';
    }

    /**
     *
     * Checks that $_SERVER values match their related regular expressions.
     *
     * @param array $server A copy of $_SERVER.
     *
     * @return bool True if they all match, false if not.
     *
     */
    protected function isServerMatch($server)
    {
        foreach ($this->server as $name => $regex) {
            $matches = $this->isServerMatchRegex($server, $name, $regex);
            if (! $matches) {
                return $this->fail(self::FAILED_SERVER, " ($name)");
            }
            $this->matches[$name] = $matches[$name];
        }

        return $this->pass();
    }

    /**
     *
     * @param array $server
     *
     * @param string $name
     *
     * @param string $regex
     *
     * @return array
     *
     */
    protected function isServerMatchRegex($server, $name, $regex)
    {
        $value = isset($server[$name])
               ? $server[$name]
               : '';
        $regex = "#(?P<{$name}>{$regex})#";
        preg_match($regex, $value, $matches);
        return $matches;
    }

    /**
     *
     * Checks that the custom Route `$is_match` callable returns true, given
     * the server values.
     *
     * @param array $server A copy of $_SERVER.
     *
     * @return bool True on a match, false if not.
     *
     */
    protected function isCustomMatch($server)
    {
        if (! $this->is_match) {
            return $this->pass();
        }

        // attempt the match
        $result = call_user_func($this->is_match, $server, $this->matches);

        // did it match?
        if (! $result) {
            return $this->fail(self::FAILED_CUSTOM);
        }

        return $this->pass();
    }

    /**
     *
     * Sets the route params from the matched values.
     *
     * @return null
     *
     */
    protected function setParams()
    {
        $this->params = $this->values;
        $this->setParamsWithMatches();
        $this->setParamsWithWildcard();

    }

    /**
     *
     * @return null
     *
     */
    protected function setParamsWithMatches()
    {
        // populate the path matches into the route values. if the path match
        // is exactly an empty string, treat it as missing/unset. (this is
        // to support optional ".format" param values.)
        foreach ($this->matches as $key => $val) {
            if (is_string($key) && $val !== '') {
                $this->params[$key] = rawurldecode($val);
            }
        }
    }

    /**
     *
     * @return null
     *
     */
    protected function setParamsWithWildcard()
    {
        if (! $this->wildcard) {
            return;
        }

        if (empty($this->params[$this->wildcard])) {
            $this->params[$this->wildcard] = array();
            return;
        }

        $this->params[$this->wildcard] = array_map(
            'rawurldecode',
            explode('/', $this->params[$this->wildcard])
        );
    }
}
