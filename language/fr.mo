��    E      D  a   l      �  �   �  d   �  j         k     |  ,   �  p   �     *  S   7  �   �     	  ,   0	  <   ]	     �	  %   �	     �	  *   �	     
     '
     F
     ^
     {
     �
     �
  A   �
  
   	       $   $  p   I  x   �  Q   3  L   �  .   �  W     �   Y  >     T   @  M   �  L   �  I   0  ?   z  M   �  J     �   S  �     �   �  �   b  $   �  �     �   �  �   �  �   �  W   `  \   �  3     "   I  >   l  :   �  %   �  u     �   �  �   s     �  y     �   �               $    '  �   6  u     u   y     �       J     �   c     �  ^   
  �   i  +       <   ,   @   i      �   -   �   ,   �   C   !     Q!  %   l!      �!  &   �!  #   �!  #   �!     ""  \   ="     �"     �"  0   �"  �   �"  �   }#  c   $  j   l$  >   �$  g   %  �   ~%  N   A&  V   �&  h   �&  S   P'  e   �'  R   
(  R   ](  d   �(  �   )  �   �)  �   �*  �   �+  *   1,  �   \,  �   @-  �   .  �   /  m    0  l   n0  @   �0  *   1  G   G1  J   �1  (   �1  �   2    �2  �   �3  !   j4  �   �4  �   45     �5      6     6             (   -   <           .   "   6   )             %   /                         !                 2   E   $   0   #       +   '          C      D           B   8                    4            	          ?            :       &          9              A          5      >                           1   *   3         
               7   ;   =   ,   @    "Clean Url" module allows to have clean, readable and search engine optimized urls for pages and resources, like https://example.net/item_set_identifier/item_identifier. A pattern for "{resource_type}", for example "[a-zA-Z0-9_-]+", is required to use the path "{path}". A short pattern for "{resource_type}", for example "[a-zA-Z0-9_-]+", is required to use the path "{path}". Additional paths Admin Interface Cannot render page: no default site was set. Check the new config file "config/clean_url.config.php" and remove the old one in the config directory of Omeka. Default path For a good seo, it’s not recommended to have multiple urls for the same resource. For identifiers, it is recommended to use a pattern that includes at least one letter to avoid confusion with internal numerical ids. Identifiers are case sensitive Identifiers have slash, so don’t escape it Item should be an Item, an ItemRepresentation or an integer. Medias Optional pattern for short identifier Other reserved routes in admin Path with placeholders, without site slug. Pattern of identifier Prefix to select an identifier Property for identifier Rename or skip prefix /page/ Rename or skip prefix /s/ See %s for more information. Select a property… Set a default site if you want to remove the part "/s/site-slug". Short path Sites and pages Skip "s/site-slug/" for default site The config of the module cannot be saved in "config/cleanurl.config.php". It is required to skip the site paths. The file "clean_url.config.php" and/or "config/clean_url.dynamic.php" in the config directory of Omeka is not writeable. The file "cleanurl.config.php" in the config directory of Omeka is not writeable. The file "config/cleanurl.config.php" at the root of Omeka is not writeable. The main site is defined in the main settings. The module "%s" was automatically deactivated because the dependencies are unavailable. The module has been rewritten and the whole configuration has been simplified. You should check your config, because the upgrade of the configuration is not automatic. The module removed tables "%s" from a previous broken install. The path "{path}" for item sets should contain one and only one item set identifier. The path "{path}" for item sets should not contain identifier "{identifier}". The path "{path}" for items should contain one and only one item identifier. The path "{path}" for items should not contain identifier "{identifier}". The path "{path}" for medias should contain an item identifier. The path "{path}" for medias should contain one and only one item identifier. The path "{path}" for medias should not contain identifier "{identifier}". The prefix "{slug}" is already set for a site, which prevents from being used as a prefix for pages. Use another prefix or rename the site. See the {link}list of reserved strings{link_end}. The prefix "{slug}" is already set for a site, which prevents from being used as a prefix. Use another prefix or rename the site. See the {link}list of reserved strings{link_end}. The prefix "{slug}" is reserved, which prevents from being used as a prefix for pages. Use another prefix. See the {link}list of reserved strings{link_end}. The prefix "{slug}" is reserved, which prevents from being used as a prefix. Use another prefix. See the {link}list of reserved strings{link_end}. The prefix is part of the identifier The sites "{site_slugs}" use a reserved string which prevents "/s/site-slug" from being removed. Rename these sites if you want to skip "/s/site-slug". See the {link}list of reserved strings{link_end}. The sites "{site_slugs}" use a reserved string which prevents the prefix for site from being removed. Rename these sites if you want to skip the prefix. See the {link}list of reserved strings{link_end}. The sites pages "{page_slugs}" use a reserved string or a site slug which prevents "/s/site-slug" from being removed. Rename these pages if you want to skip "/s/site-slug". See the {link}list of reserved strings{link_end}. The sites pages "{page_slugs}" use a reserved string which prevents the prefix for pages from being removed. Rename these pages if you want to skip the prefix. See the {link}list of reserved strings{link_end}. The slug "{slug}" is used or reserved. A random string has been automatically appended. This module cannot install its tables, because they exist already. Try to remove them first. This module has resources that cannot be installed. This module requires modules "%s". This module requires the module "%1$s", version %2$s or above. This module requires the module "%s", version %s or above. This module requires the module "%s". This option allows to fix routes for unmanaged modules. Add them in the file cleanurl.config.php or here, one by row. This prefix allows to find one identifier when there are multiple values: "ark:", "record:", or "doc =". Include space if needed. Let empty to use the first identifier. If this identifier does not exists, the Omeka resource id will be used. Unable to copy config files "config/clean_url.config.php" and/or "config/clean_url.dynamic.php" in the config directory of Omeka. Use in admin board Warning: the config of the module cannot be saved in "config/cleanurl.config.php". It is required to skip the site paths. Your previous version is too old to do a direct upgrade to the current version. Upgrade to version 3.15.13 first, or uninstall/reinstall the module. [none] page/ s/ Project-Id-Version: 
Report-Msgid-Bugs-To: 
PO-Revision-Date: 
Last-Translator: Daniel Berthereau <Daniel.fr@Berthereau.net>
Language-Team: 
Language: fr
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
X-Generator: Poedit 3.2.2
 Le module « Clean Url » permet de diffuser des urls claires, lisibles, et optimisées pour les moteurs de recherche pour les pages et les ressources, comme http://exemple.com/ma_collection/id_contenu. Un modèle pour « {resource_type} », par exemple "[a-zA-Z0-9_-]+", est nécessaire pour le chemin « {path} ». Un modèle pour « {resource_type} », par exemple "[a-zA-Z0-9_-]+", est nécessaire pour le chemin « {path} ». Chemins complémentaires Interface admin Impossible d’afficher la page : aucun site par défaut n’est défini. Vérifier le nouveau fichier de configuration « config/clean_url.config.php » et supprimer l’ancien dans le dossier « config » d’Omeka. Chemin par défaut Pour un bon SEO, il n’est pas recommandé d’avoir plusieurs urls pour une même ressource. Pour les identifiants, il est recommandé d’utiliser un modèle qui inclut au moins une lettre pour éviter la confusion avec les numéros internes. Les identifiants sont sensibles à la casse Les identifiants ont une barre « / » à ne pas échapper L’item doit être un Item, un ItemRepresentation ou un entier. Médias Modèle facultatif pour l’identifiant court Autres routes réservées en interface admin Chemin avec des chaînes réservés, non échappé et sans le site. Modèle d’un identifiant Préfixe pour trouver l’identifiant Propriété pour l’identifiant Renommer ou ignorer le préfixe /page/ Renommer ou ignorer le préfixe /s/ Voir %s pour plus d’informations. Choisir une propriété… Définissez un site par défaut si vous voulez supprimer l’élément « /s/site-slug ». Chemin court Sites et pages Enlever « s/site-slug/ » du site par défaut La configuration du module ne peut pas être enregistrée dans "config/cleanurl.config.php". Cela est nécessaire pour ignorer les chemins des sites. Le fichier "clean_url.config.php" ou "config/clean_url.dynamic.php" dans le dossier de configuration d’Omeka ne peut pas être modifié. Le fichier "cleanurl.config.php" dans le dossier de configuration d’Omeka n’est pas modifiable. Le fichier "config/cleanurl.config.php" dans le dossier de configuration d’Omeka n’est pas modifiable. Le site principal est défini dans les paramètres généraux. Le module « %s » a été automatiquement désactivé car ses dépendances ne sont plus disponibles. Le module a été entièrement réécrit et l’ensemble de la configuration a été simplifiée. Il est recommandé de vérifier la configuration car la mise à jour n’ est pas automatique. Le module a supprimé les tables « %s » depuis une installation échouée. Le chemin « {path} » pour les collections doit contenir un identifiant et un seul. Le chemin « {path} » pour les collections ne doit pas contenir l’identifiant « {identifier} ». Le chemin « {path} » pour les contenus doit contenir un identifiant et un seul. Le chemin « {path} » pour les contenus ne doit pas contenir l’identifiant « {identifier} ». Le chemin « {path} » pour les médias doit contenir un identifiant du contenu. Le chemin « {path} » pour les médias doit contenir un identifiant et un seul. Le chemin « {path} » pour les médias ne doit pas contenir l’identifiant « {identifier} ». Le préfixe « {slug} » est déjà défini pour un site, ce qui empêche de l’utiliser comme préfixe pour les pages. Utilisez un autre préfixe ou renommer le site. Voir la {link}liste des mots réservés{link_end}. Le préfixe « {slug} » est déjà défini pour un site, ce qui empêche de l’utiliser comme préfixe. Utilisez un autre préfixe ou renommer le site. Voir la {link}liste des mots réservés{link_end}. Le préfixe « {slug} » est un mot réservé, ce qui empêche de l’utiliser comme préfixe pour les pages. Utilisez un autre préfixe. Voir la {link}liste des mots réservés{link_end}. Le préfixe « {slug} » est un mot réservé, ce qui empêche de l’utiliser comme préfixe. Utilisez un autre préfixe. Voir la {link}liste des mots réservés{link_end}. Le préfixe fait partie de l’identifiant Les sites « {site_slugs} » utilisent des mots réservés, ce qui empêche de supprimer « /s/site-slug ». Renommez ces sites si vous voulez enlever « /s/site-slug ». Voir la {link}liste des mots réservés{link_end}. Les sites « {site_slugs} » utilisent des mots réservés, ce qui empêche de supprimer le préfixe des sites. Renommez ces sites si vous voulez enlever le préfixe. Voir la {link}liste des mots réservés{link_end}. Les pages de sites « {page_slugs} » utilisent des mots réservés ou un nom de site, ce qui empêche de supprimer « /s/site-slug ». Renommez ces pages si vous voulez enlever « /s/site-slug ». Voir la {link}liste des mots réservés{link_end}. Les pages de sites « {page_slugs} » utilisent des mots réservés, ce qui empêche de supprimer le préfixe des pages. Renommez ces pages si vous voulez enlever le préfixe. Voir la {link}liste des mots réservés{link_end}. Le segment « {slug} » est utilisé ou réservé. Une chaîne aléatoire a été automatiquement ajoutée. Ce module ne peut pas installer ses tables car elles existent déjà. Essayez de les supprimer manuellement. Ce module a des ressources qui ne peuvent pas être installées. Ce module requiert les modules « %s ». Ce module requiert le module « %1$s », version %2$s ou supérieure. Ce module requiert le module « %s », version « %s » ou supérieur. Ce module requiert le module « %s ». Cette option permet de corriger les routes pour les modules non gérés. On peut les ajouter dans le fichier « cleanurl.config.php » ou ici, une par ligne. Ce préfixe permet de trouver un identifiant quand une ressource en a plusieurs : « ark: », « notice: », or « doc = ». Inclure l’espace si besoin. Laisser vide pour utiliser le premier identifiant. Si l’identifiant n’existe pas, l’identifiant Omeka sera utilisé. Impossible de copier le fichier de configuration « config/clean_url.config.php » ou « config/clean_url.dynamic.php » dans le dossier de configuration d’Omeka. Utiliser dans l’interface admin Attention : la configuration du module ne peut pas être enregistrée dans « config/cleanurl.config.php ». Cela est nécessaire pour ignorer les chemins des sites. Votre version est trop ancienne pour permettre une mise à niveau directe vers la nouvelle version. Mettez d’abord à niveau vers la version 3.15.13, ou désinstallez et réinstallez le module. [aucun] page/ s/ 