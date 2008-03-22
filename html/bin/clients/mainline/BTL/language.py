# -*- coding: UTF-8 -*-
# The contents of this file are subject to the BitTorrent Open Source License
# Version 1.1 (the License).  You may not copy or use this file, in either
# source code or executable form, except in compliance with the License.  You
# may obtain a copy of the License at http://www.bittorrent.com/license/.
#
# Software distributed under the License is distributed on an AS IS basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied.  See the License
# for the specific language governing rights and limitations under the
# License.

# http://people.w3.org/rishida/names/languages.html

import gettext

class LanguageDict(dict):
    def __getitem__(self, key):
        for k in gettext._expand_lang(key):
            if self.has_key(k):
                return dict.__getitem__(self, key)
        raise KeyError(key)

language_names = LanguageDict()

language_names.update( {
    'af'   :u'Afrikaans'            ,    'bg'   :u'Български'            ,
    'da'   :u'Dansk'                ,    'ca'   :u'Català'               ,
    'cs'   :u'Čeština'              ,    'de'   :u'Deutsch'              ,
    'en'   :u'English'              ,    'es'   :u'Español'              ,
    'es_MX':u'Español de Mexico '   ,    'fr'   :u'Français'             ,
    'el'   :u'Ελληνικά'             ,    'he'   :u'עברית'                ,
    'hu'   :u'Magyar'               ,    'it'   :u'Italiano'             ,
    'is'   :u'Íslenska'             ,    'ja'   :u'日本語'            ,
    'ko'   :u'한국어'            ,'nl'   :u'Nederlands'           ,
    'nb_NO':u'Norsk bokmål'         ,    'pl'   :u'Polski'               ,
    'pt'   :u'Português'            ,    'pt_BR':u'Português do Brasil'  ,
    'ro'   :u'Română'               ,    'ru'   :u'Русский'              ,
    'sk'   :u'Slovenský'            ,    'sl'   :u'Slovensko'            ,
    'sv'   :u'Svenska'              ,    'tr'   :u'Türkçe'               ,
    'vi'   :u'Tiê?ng Viê?t'           ,
    'zh_CN':u'简体中文'               , # Simplified
    'zh_TW':u'繁體中文'               , # Traditional
    } )

unfinished_language_names = {
    'ar'   :u'العربية'       ,    'bs'   :u'Bosanski'             ,
    'eo'   :u'Esperanto'            ,    'eu'   :u'Euskara'              ,
    'et'   :u'Eesti'                ,    'fi'   :u'Suomi'                ,
    'fa'   :u'فارسی'                ,    'ga'   :u'Gaeilge'              ,
    'gl'   :u'Galego'               ,    'hr'   :u'Hrvatski'             ,
    'hy'   :u'Հայերեն'       ,    'in'   :u'Bahasa indonesia'     ,
    'ka'   :u'ქართული ენა',    'lt'   :u'Lietuvių'        ,
    'ms'   :u'Bahasa melayu'        ,    'ml'   :u'Malayalam'            ,
    'sq'   :u'Shqipe'                ,    'th'   :u'ภาษาไทย'              ,
    'tlh'  :u'tlhIngan-Hol'         ,    'uk'   :u'Українська'           ,
    'hi'   :u'हिंदी'                  ,    'cy'   :u'Cymraeg'              ,
    'nn_NO':u'Norsk Nynorsk'        ,    'te'   :u'	తెలుగు'             ,
    }

#language_names.update(unfinished_language_names)

class LanguageCodeList(list):
    def index(self, value):
        for v in gettext._expand_lang(value):
            try:
                i = list.index(self, v)
                return i
            except ValueError:
                pass
        raise ValueError('%v not in list'%value)

languages = LanguageCodeList()
languages.extend(language_names.keys())
languages.sort()

# windows codepage to locale mapping
locale_sucks = {
    0x0436: "af",       # Afrikaans
    0x3801: "ar_AE",    # Arabic - United Arab Emirates
    0x3C01: "ar_BH",    # Arabic - Bahrain
    0x1401: "ar_DZ",    # Arabic - Algeria
    0x0C01: "ar_EG",    # Arabic - Egypt
    0x0801: "ar_IQ",    # Arabic - Iraq
    0x2C01: "ar_JO",    # Arabic - Jordan
    0x3401: "ar_KW",    # Arabic - Kuwait
    0x3001: "ar_LB",    # Arabic - Lebanon
    0x1001: "ar_LY",    # Arabic - Libya
    0x1801: "ar_MA",    # Arabic - Morocco
    0x2001: "ar_OM",    # Arabic - Oman
    0x4001: "ar_QA",    # Arabic - Qatar
    0x0401: "ar_SA",    # Arabic - Saudi Arabia
    0x2801: "ar_SY",    # Arabic - Syria
    0x1C01: "ar_TN",    # Arabic - Tunisia
    0x2401: "ar_YE",    # Arabic - Yemen
    0x082C: "az_AZ",    # Azeri - Cyrillic
    0x0423: "be",       # Belarusian
    0x0402: "bg",       # Bulgarian
    0x0403: "ca",       # Catalan
    0x0405: "cs",       # Czech
    0x0406: "da",       # Danish
    0x0007: "de",       # German
    0x0C07: "de_AT",    # German - Austria
    0x0807: "de_CH",    # German - Switzerland
    0x0407: "de_DE",    # German - Germany
    0x1407: "de_LI",    # German - Liechtenstein
    0x1007: "de_LU",    # German - Luxembourg
    0x0408: "el",       # Greek
    0x0C09: "en_AU",    # English - Australia
    0x2809: "en_BZ",    # English - Belize
    0x1009: "en_CA",    # English - Canada
    0x2409: "en_CB",    # English - Carribbean
    0x0809: "en_GB",    # English - United Kingdom
    0x1809: "en_IE",    # English - Ireland
    0x2009: "en_JM",    # English - Jamaica
    0x1409: "en_NZ",    # English - New Zealand
    0x3409: "en_PH",    # English - Phillippines
    0x2C09: "en_TT",    # English - Trinidad
    0x0409: "en_US",    # English - United States
    0x1C09: "en_ZA",    # English - South Africa
    0x000A: "es",       # Spanish (added)
    0x2C0A: "es_AR",    # Spanish - Argentina
    0x400A: "es_BO",    # Spanish - Bolivia
    0x340A: "es_CL",    # Spanish - Chile
    0x240A: "es_CO",    # Spanish - Colombia
    0x140A: "es_CR",    # Spanish - Costa Rica
    0x1C0A: "es_DO",    # Spanish - Dominican Republic
    0x300A: "es_EC",    # Spanish - Ecuador
    0x040a: "es_ES",    # Spanish - Spain
    0x100A: "es_GT",    # Spanish - Guatemala
    0x480A: "es_HN",    # Spanish - Honduras
    0x080A: "es_MX",    # Spanish - Mexico
    0x4C0A: "es_NI",    # Spanish - Nicaragua
    0x180A: "es_PA",    # Spanish - Panama
    0x280A: "es_PE",    # Spanish - Peru
    0x500A: "es_PR",    # Spanish - Puerto Rico
    0x3C0A: "es_PY",    # Spanish - Paraguay
    0x440A: "es_SV",    # Spanish - El Salvador
    0x380A: "es_UY",    # Spanish - Uruguay
    0x200A: "es_VE",    # Spanish - Venezuela
    0x0425: "et",       # Estonian
    0x0009: "en",       # English (added)
    0x042D: "eu",       # Basque
    0x0429: "fa",       # Farsi
    0x040B: "fi",       # Finnish
    0x0438: "fo",       # Faroese
    0x000C: "fr",       # French (added) 
    0x080C: "fr_BE",    # French - Belgium
    0x0C0C: "fr_CA",    # French - Canada
    0x100C: "fr_CH",    # French - Switzerland
    0x040C: "fr_FR",    # French - France
    0x140C: "fr_LU",    # French - Luxembourg
    0x043C: "gd",       # Gaelic - Scotland
    0x083C: "gd_IE",    # Gaelic - Ireland
    0x040D: "he",       # Hebrew
    0x0439: "hi",       # Hindi
    0x041A: "hr",       # Croatian
    0x040E: "hu",       # Hungarian
    0x042B: "hy",       # Armenian
    0x0421: "id",       # Indonesian
    0x040F: "is",       # Icelandic
    0x0010: "it",       # Italian (added)
    0x0810: "it_CH",    # Italian - Switzerland
    0x0410: "it_IT",    # Italian - Italy
    0x0411: "ja",       # Japanese
    0x0412: "ko",       # Korean
    0x0427: "lt",       # Lithuanian
    0x0426: "lv",       # Latvian
    0x042F: "mk",       # FYRO Macedonian
    0x044E: "mr",       # Marathi
    0x083E: "ms_BN",    # Malay - Brunei
    0x043E: "ms_MY",    # Malay - Malaysia
    0x043A: "mt",       # Maltese
    0x0013: "nl",       # Dutch (added)
    0x0813: "nl_BE",    # Dutch - Belgium
    0x0413: "nl_NL",    # Dutch - The Netherlands
    0x0814: "no_NO",    # Norwegian - Nynorsk
    0x0414: "nb_NO",    # Norwegian - Bokmal (?)
    0x0415: "pl",       # Polish
    0x0016: "pt",       # Portuguese (added)
    0x0416: "pt_BR",    # Portuguese - Brazil
    0x0816: "pt_PT",    # Portuguese - Portugal
    0x0417: "rm",       # Raeto-Romance
    0x0418: "ro",       # Romanian - Romania
    0x0818: "ro_MO",    # Romanian - Moldova
    0x0419: "ru",       # Russian
    0x0819: "ru_MO",    # Russian - Moldova
    0x044F: "sa",       # Sanskrit
    0x042E: "sb",       # Sorbian
    0x041B: "sk",       # Slovak
    0x0424: "sl",       # Slovenian
    0x041C: "sq",       # Albanian
    0x081A: "sr_SP",    # Serbian - Latin
    0x001D: "sv",       # Swedish (added)
    0x081D: "sv_FI",    # Swedish - Finland
    0x041D: "sv_SE",    # Swedish - Sweden
    0x0441: "sw",       # Swahili
    0x0430: "sx",       # Sutu
    0x0449: "ta",       # Tamil
    0x041E: "th",       # Thai
    0x0432: "tn",       # Setsuana
    0x041F: "tr",       # Turkish
    0x0431: "ts",       # Tsonga
    0X0444: "tt",       # Tatar
    0x0422: "uk",       # Ukrainian
    0x0420: "ur",       # Urdu
    0x0443: "uz_UZ",    # Uzbek - Latin
    0x042A: "vi",       # Vietnamese
    0x0434: "xh",       # Xhosa
    0x043D: "yi",       # Yiddish
    0x0804: "zh_CN",    # Chinese - China
    0x0C04: "zh_HK",    # Chinese - Hong Kong S.A.R.
    0x1404: "zh_MO",    # Chinese - Macau S.A.R
    0x1004: "zh_SG",    # Chinese - Singapore
    0x0404: "zh_TW",    # Chinese - Taiwan
    0x0435: "zu",       # Zulu
}

if __name__ == '__main__':
    from BTL.obsoletepythonsupport import set
    internal = set([x.lower() for x in languages])
    windows = set(locale_sucks.values())
    if not windows.issuperset(internal):
        diff = list(internal.difference(windows))
        diff.sort()
        print diff
