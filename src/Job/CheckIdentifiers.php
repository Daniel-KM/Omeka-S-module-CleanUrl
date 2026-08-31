<?php declare(strict_types=1);

namespace CleanUrl\Job;

use CleanUrl\Stdlib\IdentifierChecker;
use Common\Stdlib\PsrMessage;
use Omeka\Job\AbstractJob;

/**
 * List the identifiers that cannot be used to build a clean url.
 *
 * The job only reports: an identifier is a metadata with an external meaning
 * (ark, shelf mark), so it is never fixed automatically. Two remediations are
 * possible: widen the pattern in the config, that is generally the right one,
 * or normalize the values with the module Bulk Edit, that already replaces a
 * string or a regex in a property. The report gives the resource ids, so the
 * resources can be selected for such a bulk edit.
 */
class CheckIdentifiers extends AbstractJob
{
    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $logger = $services->get('Omeka\Logger');
        $settings = $services->get('Omeka\Settings');
        $checker = new IdentifierChecker($services->get('Omeka\Connection'));

        $dir = OMEKA_PATH . '/files/cleanurl';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $filename = 'check-identifiers-' . date('Y-m-d_H-i-s') . '.tsv';
        $filepath = $dir . '/' . $filename;

        $handle = fopen($filepath, 'w');
        if (!$handle) {
            $logger->err(new PsrMessage(
                'Unable to write the report "{filepath}".', // @translate
                ['filepath' => $filepath]
            ));
            return;
        }

        fwrite($handle, implode("\t", [
            'resource_type',
            'identifier_type',
            'resource_id',
            'identifier',
            'characters_to_add',
        ]) . "\n");

        $totals = ['total' => 0, 'invalid' => 0];
        foreach ($checker->resourceTypes() as $resourceType => $resourceName) {
            $options = $settings->get('cleanurl_' . $resourceType);
            if (!is_array($options)) {
                continue;
            }
            foreach ($checker->identifierModes($options, $resourceType) as $short) {
                if ($this->shouldStop()) {
                    fclose($handle);
                    $logger->warn(new PsrMessage(
                        'The check was stopped. The partial report is "{filepath}".', // @translate
                        ['filepath' => $filepath]
                    ));
                    return;
                }

                $identifierType = $resourceType . '_identifier' . ($short ? '_short' : '');
                $onInvalid = function (int $resourceId, string $identifier, array $characters) use ($handle, $resourceName, $identifierType): void {
                    fwrite($handle, implode("\t", [
                        $resourceName,
                        $identifierType,
                        $resourceId,
                        strtr($identifier, ["\t" => ' ', "\n" => ' ', "\r" => ' ']),
                        implode(' ', $characters),
                    ]) . "\n");
                };

                $check = $checker->checkResourceType($resourceName, $options, $short, $onInvalid);
                $totals['total'] += $check['total'];
                $totals['invalid'] += $check['invalid'];

                $logger->notice(new PsrMessage(
                    '{resource_name} ({identifier_type}): {count} identifiers on {total} have no clean url. Characters to add to the pattern: {characters}', // @translate
                    [
                        'resource_name' => $resourceName,
                        'identifier_type' => $identifierType,
                        'count' => $check['invalid'],
                        'total' => $check['total'],
                        'characters' => $check['characters'] ? implode(' ', $check['characters']) : '-',
                    ]
                ));
            }
        }

        fclose($handle);

        $logger->notice(new PsrMessage(
            '{count} identifiers on {total} have no clean url. The report is "{filepath}".', // @translate
            [
                'count' => $totals['invalid'],
                'total' => $totals['total'],
                'filepath' => $filepath,
            ]
        ));
    }
}
