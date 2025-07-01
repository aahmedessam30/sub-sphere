<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Traits;

/**
 * Trait HasTranslatableHelpers
 * 
 * Provides helper methods for working with translatable fields.
 * This trait extends Spatie's translatable functionality with
 * convenient methods and backward compatibility.
 * Requires the model to use Spatie\Translatable\HasTranslations trait.
 */
trait HasTranslatableHelpers
{
    /**
     * Get translation for a field with fallback support
     * 
     * @param string $field The field name
     * @param string|null $locale The locale (defaults to app locale)
     * @param string $fallbackLocale The fallback locale (defaults to 'en')
     * @param mixed $default Default value if translation not found
     * @return mixed
     */
    public function getTranslatedAttribute(string $field, ?string $locale = null, string $fallbackLocale = 'en', mixed $default = null): mixed
    {
        $locale = $locale ?: app()->getLocale();

        // Try to get translation for requested locale
        $translation = $this->getTranslation($field, $locale);

        if ($translation !== null && $translation !== '') {
            return $translation;
        }

        // Fall back to fallback locale
        if ($locale !== $fallbackLocale) {
            $translation = $this->getTranslation($field, $fallbackLocale);

            if ($translation !== null && $translation !== '') {
                return $translation;
            }
        }

        // Return default value
        return $default;
    }

    /**
     * Get the name attribute with locale fallback
     * 
     * @param string|null $locale
     * @return string|null
     */
    public function getLocalizedName(?string $locale = null): ?string
    {
        if (!in_array('name', $this->translatable ?? [])) {
            return $this->name ?? null;
        }

        return $this->getTranslatedAttribute('name', $locale, 'en', $this->slug ?? null);
    }

    /**
     * Get the description attribute with locale fallback
     * 
     * @param string|null $locale
     * @return string|null
     */
    public function getLocalizedDescription(?string $locale = null): ?string
    {
        if (!in_array('description', $this->translatable ?? [])) {
            return $this->description ?? null;
        }

        return $this->getTranslatedAttribute('description', $locale, 'en', null);
    }

    /**
     * Get the label attribute with locale fallback (for PlanPricing)
     * 
     * @param string|null $locale
     * @return string|null
     */
    public function getLocalizedLabel(?string $locale = null): ?string
    {
        if (!in_array('label', $this->translatable ?? [])) {
            return $this->label ?? null;
        }

        return $this->getTranslatedAttribute('label', $locale, 'en', null);
    }

    /**
     * Backward compatibility: Support array-like access to translations
     * This maintains compatibility with existing code that accessed translations
     * as $model->name['en'] while using Spatie's translation system underneath
     * 
     * @param string $field
     * @return array
     */
    public function getTranslationsArray(string $field): array
    {
        if (!in_array($field, $this->translatable ?? [])) {
            return [];
        }

        return $this->getTranslations($field);
    }

    /**
     * Get all available locales for a specific field
     * 
     * @param string $field
     * @return array
     */
    public function getAvailableLocales(string $field): array
    {
        if (!in_array($field, $this->translatable ?? [])) {
            return [];
        }

        return array_keys($this->getTranslations($field));
    }
}
