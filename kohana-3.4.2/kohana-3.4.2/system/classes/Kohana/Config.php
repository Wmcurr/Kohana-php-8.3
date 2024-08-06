<?php
declare(strict_types=1);

/**
 * Wrapper for configuration arrays. Multiple configuration readers can be
 * attached to allow loading configuration from files, database, etc.
 *
 * Configuration directives cascade across config sources in the same way that
 * files cascade across the filesystem.
 *
 * Directives from sources high in the sources list will override ones from those
 * below them.
 * @php 8.3
 * @package    Kohana 2024
 * @category   Configuration
 */
class Kohana_Config
{
    /**
     * @var array List of configuration readers
     */
    protected array $_sources = [];
    
    /**
     * @var array List of config groups
     */
    protected array $_groups = [];

    /**
     * Attach a configuration reader. By default, the reader will be added as
     * the first used reader. However, if the reader should be used only when
     * all other readers fail, use `false` for the second parameter.
     *
     *     $config->attach($reader);        // Try first
     *     $config->attach($reader, false); // Try last
     *
     * @param   Kohana_Config_Source $source instance
     * @param   bool                 $first  add the reader as the first used object
     * @return  self
     */
    public function attach(Kohana_Config_Source $source, bool $first = true): self
    {
        if ($first) {
            array_unshift($this->_sources, $source);
        } else {
            $this->_sources[] = $source;
        }

        $this->_groups = [];

        return $this;
    }

    /**
     * Detach a configuration reader.
     *
     *     $config->detach($reader);
     *
     * @param   Kohana_Config_Source $source instance
     * @return  self
     */
    public function detach(Kohana_Config_Source $source): self
    {
        if (($key = array_search($source, $this->_sources, true)) !== false) {
            unset($this->_sources[$key]);
        }

        return $this;
    }

    /**
     * Load a configuration group. Searches all the config sources, merging all the
     * directives found into a single config group.  Any changes made to the config
     * in this group will be mirrored across all writable sources.
     *
     *     $array = $config->load($name);
     *
     * See [Kohana_Config_Group] for more info
     *
     * @param   string  $group  configuration group name
     * @return  Kohana_Config_Group|array|null
     * @throws  Kohana_Exception
     */
    public function load(string $group)
    {
        if (count($this->_sources) === 0) {
            throw new Kohana_Exception('No configuration sources attached');
        }

        if ($group === '') {
            throw new Kohana_Exception("Need to specify a config group");
        }

        if (strpos($group, '.') !== false) {
            list($group, $path) = explode('.', $group, 2);
        }

        if (isset($this->_groups[$group])) {
            return isset($path) ? Arr::path($this->_groups[$group], $path) : $this->_groups[$group];
        }

        $config = [];

        $sources = array_reverse($this->_sources);

        foreach ($sources as $source) {
            if ($source instanceof Kohana_Config_Reader) {
                $sourceConfig = $source->load($group);
                if ($sourceConfig) {
                    $config = Arr::merge($config, $sourceConfig);
                }
            }
        }

        $this->_groups[$group] = new Config_Group($this, $group, $config);

        return isset($path) ? Arr::path($config, $path) : $this->_groups[$group];
    }

    /**
     * Copy one configuration group to all of the other writers.
     *
     *     $config->copy($name);
     *
     * @param   string  $group  configuration group name
     * @return  self
     */
    public function copy(string $group): self
    {
        $config = $this->load($group);

        foreach ($config->as_array() as $key => $value) {
            $this->_write_config($group, $key, $value);
        }

        return $this;
    }

    /**
     * Callback used by the config group to store changes made to configuration
     *
     * @param string    $group  Group name
     * @param string    $key    Variable name
     * @param mixed     $value  The new value
     * @return self
     */
    public function _write_config(string $group, string $key, $value): self
    {
        foreach ($this->_sources as $source) {
            if ($source instanceof Kohana_Config_Writer) {
                $source->write($group, $key, $value);
            }
        }

        return $this;
    }
}
