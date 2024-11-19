<?php

declare(strict_types=1);

class Kohana_I18n
{
    public static string $lang = 'en-us';
    public static string $source = 'en-us';
    protected static array $_cache = [];
    protected static array $_fileModifiedTimes = [];
    protected static array $_trieCache = [];

    // Встроенная структура Trie
    private static array $trieNodes = [];

    /**
     * Установка и получение текущего языка.
     */
    public static function lang(?string $lang = null): string
    {
        if ($lang && $lang !== self::$lang) {
            self::$lang = strtolower(str_replace([' ', '_'], '-', $lang));
            self::clearCache(); // Очистка кэша для нового языка
        }
        return self::$lang;
    }

    /**
     * Очистка кэша переводов и Trie.
     */
    protected static function clearCache(): void
    {
        self::$_cache = [];
        self::$_fileModifiedTimes = [];
        self::$_trieCache = [];
        self::$trieNodes = [];
    }

    /**
     * Возвращает перевод строки с поддержкой контекста.
     *
     * @param string $string Строка для перевода.
     * @param string|null $lang Язык перевода.
     * @param string|null $context Контекст перевода.
     * @param int $maxPhraseLength Максимальная длина фразы для поиска.
     * @return string Переведенная строка.
     */
public static function get(string $string, ?string $lang = null, ?string $context = null, int $maxPhraseLength = 5): string
{
    $lang ??= self::$lang;

    // Извлечение ведущей нумерации
    $numbering = '';
    $textToTranslate = $string;
if (preg_match('/^((?:\d+\.)*\d+\.?\s*)(.*)$/u', $string, $matches)) {
    $numbering = $matches[1]; // Например, "2.1.1 " или "2.1.1. "
    $textToTranslate = $matches[2]; // Остальной текст
}


    if (self::containsHTML($textToTranslate)) {
        $translatedText = self::translateHTML($textToTranslate, $lang);
    } else {
        $table = self::load($lang, $context);

        // Проверяем полное совпадение без нормализации
        if (isset($table[$textToTranslate])) {
            $translatedText = $table[$textToTranslate];
        } else {
            // Нормализуем строку и проверяем ещё раз
            $normalizedString = self::normalizeText($textToTranslate);
            $normalizedStringLower = mb_strtolower($normalizedString);
            if (isset($table[$normalizedStringLower])) {
                $translatedText = $table[$normalizedStringLower];
            } else {
                $words = explode(' ', $normalizedString);
                $wordsLower = array_map('mb_strtolower', $words);
                if (count($words) === 1) {
                    $translatedText = $table[$wordsLower[0]] ?? $textToTranslate;
                } else {
                    $cacheKey = $context ? "{$lang}_{$context}" : $lang;
                    if (!isset(self::$_trieCache[$cacheKey])) {
                        $translatedText = implode(' ', $words);
                    } else {
                        $translatedParts = [];
                        $i = 0;
                        $originalWords = explode(' ', $textToTranslate);
                        while ($i < count($wordsLower)) {
                            $result = self::trieSearch(self::$_trieCache[$cacheKey], $wordsLower, $i, $maxPhraseLength);
                            if ($result !== null) {
                                $translatedParts[] = $result['translation'];
                                $i += $result['length'];
                            } else {
                                $currentWordOriginal = $originalWords[$i];
                                $currentWordLower = $wordsLower[$i];
                                $translatedWord = $table[$currentWordLower] ?? $currentWordOriginal;
                                $translatedParts[] = $table[$words[$i]] ?? $words[$i];
                                $i++;
                            }
                        }
                        $translatedText = implode(' ', $translatedParts);
                    }
                }
            }
        }
    }

    // Добавляем обратно нумерацию, если она была
    return $numbering . $translatedText;
}

    /**
     * Проверка на наличие HTML.
     */
    protected static function containsHTML(string $string): bool
    {
        return (bool) preg_match('/<\/?[a-z][\s\S]*>/i', $string);
    }

/**
 * Нормализация текста для поиска перевода.
 */
protected static function normalizeText(string $text): string
{
    
    // Удаление лишних пробелов
    $text = preg_replace('/\s+/', ' ', $text);
    
    // Удаление пробелов после знаков пунктуации
    $text = preg_replace('/([.,!?])\s+/', '$1', $text);
    
    return trim($text);
}

    /**
     * Перевод HTML-контента с сохранением разметки.
     */
protected static function translateHTML(string $html, string $lang): string
{
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    $xpath = new DOMXPath($dom);

    // Перевод текстовых узлов
    $query = "//text()[not(ancestor::script) and not(ancestor::style)]";
    foreach ($xpath->query($query) as $node) {
        $originalText = trim($node->nodeValue);
        if (empty($originalText)) {
            continue;
        }

        $translatedText = self::get($originalText, $lang);
        if ($translatedText !== $originalText) {
            $node->nodeValue = $translatedText;
        }
    }

    // Перевод атрибутов
    $attrQuery = "//*[@placeholder or @title]"; // можно добавить другие атрибуты, если требуется
    foreach ($xpath->query($attrQuery) as $element) {
        foreach (['placeholder', 'title'] as $attr) {
            if ($element->hasAttribute($attr)) {
                $originalAttrValue = $element->getAttribute($attr);
                $translatedAttrValue = self::get($originalAttrValue, $lang);
                if ($translatedAttrValue !== $originalAttrValue) {
                    $element->setAttribute($attr, $translatedAttrValue);
                }
            }
        }
    }

    $html = '';
    $body = $dom->getElementsByTagName('body')->item(0);
    if ($body) {
        foreach ($body->childNodes as $node) {
            $html .= $dom->saveHTML($node);
        }
    }
    
    return $html ?: $dom->saveHTML();
}



/**
 * Загрузка переводов с поддержкой динамического обновления и контекста.
 */
protected static function load(string $lang, ?string $context = ''): array
{
    $cacheKey = $context ? "{$lang}_{$context}" : $lang;

    // Check the cache and the freshness of translations
    if (isset(self::$_cache[$cacheKey]) && !self::translationsOutdated($lang, $context)) {
        return self::$_cache[$cacheKey];
    }

    $table = [];
    $parts = explode('-', $lang);

    do {
        $path = implode(DIRECTORY_SEPARATOR, $parts) . ($context ? "/{$context}" : '');

        if ($files = Kohana::find_file('i18n', $path, null, true)) {
            foreach ($files as $file) {
                $ext = pathinfo($file, PATHINFO_EXTENSION);

                if ($ext === 'json') {
                    // Loading JSON file
                    $jsonContent = file_get_contents($file);
                    $translations = json_decode($jsonContent, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($translations)) {
                        // Normalize keys to lowercase
                        $normalizedTranslations = [];
                        foreach ($translations as $phrase => $translation) {
                            $normalizedPhrase = mb_strtolower(self::normalizeText($phrase));
                            $normalizedTranslations[$normalizedPhrase] = $translation;
                        }
                        $table = array_replace_recursive($table, $normalizedTranslations);
                    }
                } else {
                    // Loading PHP file as an array
                    $translations = Kohana::load($file);
                    if (is_array($translations)) {
                        // Normalize keys to lowercase
                        $normalizedTranslations = [];
                        foreach ($translations as $phrase => $translation) {
                            $normalizedPhrase = mb_strtolower(self::normalizeText($phrase));
                            $normalizedTranslations[$normalizedPhrase] = $translation;
                        }
                        $table = array_replace_recursive($table, $normalizedTranslations);
                    }
                }

                // Save the file's modification time
                self::$_fileModifiedTimes[$file] = filemtime($file);
            }
        }

        array_pop($parts);
    } while ($parts);

    // Cache the translation table
    self::$_cache[$cacheKey] = $table;

    // Build and cache the Trie structure
    self::buildTrie($lang, $context);

    return $table;
}


    /**
     * Проверка изменений файлов переводов для динамического обновления кэша.
     */
    protected static function translationsOutdated(string $lang, ?string $context = null): bool
    {
        $parts = explode('-', $lang);
        do {
            $path = implode(DIRECTORY_SEPARATOR, $parts) . ($context ? "/{$context}" : '');

            if ($files = Kohana::find_file('i18n', $path, null, true)) {
                foreach ($files as $file) {
                    $modifiedTime = filemtime($file);
                    // Проверка, изменился ли файл после последней загрузки
                    if (!isset(self::$_fileModifiedTimes[$file]) || self::$_fileModifiedTimes[$file] !== $modifiedTime) {
                        return true;
                    }
                }
            }

            array_pop($parts);
        } while ($parts);

        return false;
    }


/**
 * Построение Trie из таблицы переводов.
 *
 * @param string $lang
 * @param string|null $context
 */
protected static function buildTrie(string $lang, ?string $context = ''): void
{
    $cacheKey = $context ? "{$lang}_{$context}" : $lang;

    if (!isset(self::$_cache[$cacheKey])) {
        // Если таблица переводов ещё не загружена, загружаем её
        self::load($lang, $context);
    }

    $table = self::$_cache[$cacheKey];
    $trie = [];

    foreach ($table as $phrase => $translation) {
        // Нормализуем фразу перед разбиением на слова
        $normalizedPhrase = self::normalizeText($phrase);
        $words = explode(' ', $normalizedPhrase);
        $node = &$trie;

        // Добавляем каждое слово как отдельный узел
        foreach ($words as $word) {
            if (!isset($node[$word])) {
                $node[$word] = [];
            }
            $node = &$node[$word];
        }

        // Сохраняем перевод для всей фразы
        $node['__end__'] = $translation;

        // Также добавляем каждое слово как отдельный элемент, если оно еще не существует
        foreach ($words as $word) {
            if (!isset($trie[$word]['__end__'])) {
                $trie[$word]['__end__'] = $table[$word] ?? $word; // используем перевод или оставляем слово
            }
        }

        unset($node);
    }

    self::$_trieCache[$cacheKey] = $trie;
}



/**
 * Поиск в Trie самой длинной подходящей фразы.
 *
 * @param array $trie Структура Trie.
 * @param array $words Массив слов строки для перевода.
 * @param int $startIndex Индекс начала поиска.
 * @param int $maxPhraseLength Максимальная длина фразы.
 * @return array|null
 */
protected static function trieSearch(array $trie, array $words, int $startIndex, int $maxPhraseLength = 5): ?array 
{
    $remainingWords = count($words) - $startIndex;
    $maxLength = min($maxPhraseLength, $remainingWords);
    $bestMatch = null;

    for ($length = $maxLength; $length > 0; $length--) {
        $node = $trie;
        $translation = null;

        for ($i = 0; $i < $length; $i++) {
            $word = strtolower($words[$startIndex + $i]);
            if (!isset($node[$word])) {
                $translation = null;
                break;
            }
            $node = $node[$word];
            if (isset($node['__end__'])) {
                $translation = $node['__end__'];
            }
        }

        if ($translation !== null) {
            return [
                'translation' => $translation,
                'length' => $length
            ];
        }
    }

    return null;
}



    /**
     * Получение перевода строки с поддержкой контекста и Trie.
     *
     * @param string $string Строка для перевода.
     * @param string|null $lang Язык перевода.
     * @param string|null $context Контекст перевода.
     * @param int $maxPhraseLength Максимальная длина фразы для поиска.
     * @return string Переведенная строка.
     */
    public static function getTranslation(string $string, ?string $lang = null, ?string $context = null, int $maxPhraseLength = 5): string
    {
        return self::get($string, $lang, $context, $maxPhraseLength);
    }
}

// Глобальная функция перевода с поддержкой контекста
function __(string $string, ?array $values = null, ?string $lang = null, ?string $context = null): string
{
    $lang ??= Kohana_I18n::$lang;
    $string = Kohana_I18n::getTranslation($string, $lang, $context);
    return empty($values) ? $string : strtr($string, $values);
}
