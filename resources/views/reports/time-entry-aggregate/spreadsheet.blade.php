@use('App\Enums\ExportFormat')
@use('Brick\Math\BigDecimal')
@use('PhpOffice\PhpSpreadsheet\Cell\DataType')
@use('PhpOffice\PhpSpreadsheet\Style\NumberFormat')
@use('Carbon\CarbonInterval')
@use('App\Enums\TimeEntryAggregationType')
@inject('interval', 'App\Service\IntervalService')
@php
    $hasThirdGroup = isset($thirdGroup) && $thirdGroup !== null;
@endphp
<table>
    <thead>
    <tr>
        <th style="border: 1px solid black; font-weight: bold;" data-type="{{ DataType::TYPE_STRING }}">
            {{ $group->description() }}
        </th>
        <th style="border: 1px solid black; font-weight: bold;" data-type="{{ DataType::TYPE_STRING }}">
            {{ $subGroup->description() }}
        </th>
        @if($hasThirdGroup)
        <th style="border: 1px solid black; font-weight: bold;" data-type="{{ DataType::TYPE_STRING }}">
            {{ $thirdGroup->description() }}
        </th>
        @endif
        <th style="border: 1px solid black; font-weight: bold;" data-type="{{ DataType::TYPE_STRING }}">
            Duration
        </th>
        <th style="border: 1px solid black; font-weight: bold;" data-type="{{ DataType::TYPE_STRING }}">
            Duration (decimal)
        </th>
        <th style="border: 1px solid black; font-weight: bold;" data-type="{{ DataType::TYPE_STRING }}">
            Amount ({{ Str::upper($currency) }})
        </th>
    </tr>
    </thead>
    <tbody>
    @php
        $counter = 1;
        $totalDuration = 0;
        $totalCost = 0;
    @endphp
    @foreach($data['grouped_data'] as $group1Entry)
        @foreach($group1Entry['grouped_data'] as $group2Entry)
            @php
                $group3Entries = $hasThirdGroup ? ($group2Entry['grouped_data'] ?? []) : [$group2Entry];
            @endphp
            @foreach($group3Entries as $group3Entry)
            @php
                $leafEntry = $hasThirdGroup ? $group3Entry : $group2Entry;
                $duration = CarbonInterval::seconds($leafEntry['seconds']);
            @endphp
            <tr>
                @if($exportFormat === ExportFormat::ODS || $exportFormat === ExportFormat::CSV)
                    @if ($group === TimeEntryAggregationType::Billable)
                        <td style="border: 1px solid black;" data-type="{{ DataType::TYPE_STRING }}">
                            {{ $group1Entry['key'] ? 'Yes' : 'No' }}
                        </td>
                    @else
                        <td style="border: 1px solid black;" data-type="{{ DataType::TYPE_STRING }}">
                            {{ $group1Entry['description'] ?? $group1Entry['key'] ?? '-' }}
                        </td>
                    @endif
                    @if ($subGroup === TimeEntryAggregationType::Billable)
                        <td style="border: 1px solid black;" data-type="{{ DataType::TYPE_STRING }}">
                            {{ $group2Entry['key'] ? 'Yes' : 'No' }}
                        </td>
                    @else
                        <td style="border: 1px solid black;" data-type="{{ DataType::TYPE_STRING }}">
                            {{ $group2Entry['description'] ?? $group2Entry['key'] ?? '-' }}
                        </td>
                    @endif
                    @if($hasThirdGroup)
                        @if ($thirdGroup === TimeEntryAggregationType::Billable)
                            <td style="border: 1px solid black;" data-type="{{ DataType::TYPE_STRING }}">
                                {{ $leafEntry['key'] ? 'Yes' : 'No' }}
                            </td>
                        @else
                            <td style="border: 1px solid black;" data-type="{{ DataType::TYPE_STRING }}">
                                {{ $leafEntry['description'] ?? $leafEntry['key'] ?? '-' }}
                            </td>
                        @endif
                    @endif
                    <td style="border: 1px solid black;" data-type="{{ DataType::TYPE_STRING }}">
                        {{ $interval->format($duration) }}
                    </td>
                    <td style="border: 1px solid black;" data-type="{{ DataType::TYPE_STRING }}">
                        {{ round($duration->totalHours, 2) }}
                    </td>
                    @if($showBillableRate)
                    <td style="border: 1px solid black;" data-type="{{ DataType::TYPE_STRING }}">
                        {{ round(BigDecimal::ofUnscaledValue($group2Entry['cost'], 2)->toFloat(), 2) }}
                    </td>
                    @endif
                @else
                    @if ($group === TimeEntryAggregationType::Billable)
                        <td style="border: 1px solid black;" data-type="{{ DataType::TYPE_STRING }}">
                            {{ $group1Entry['key'] ? 'Yes' : 'No' }}
                        </td>
                    @else
                        <td style="border: 1px solid black;" data-type="{{ DataType::TYPE_STRING }}">
                            {{ $group1Entry['description'] ?? $group1Entry['key'] ?? '-' }}
                        </td>
                    @endif
                    @if ($subGroup === TimeEntryAggregationType::Billable)
                        <td style="border: 1px solid black;" data-type="{{ DataType::TYPE_STRING }}">
                            {{ $group2Entry['key'] ? 'Yes' : 'No' }}
                        </td>
                    @else
                        <td style="border: 1px solid black;" data-type="{{ DataType::TYPE_STRING }}">
                            {{ $group2Entry['description'] ?? $group2Entry['key'] ?? '-' }}
                        </td>
                    @endif
                    @if($hasThirdGroup)
                        @if ($thirdGroup === TimeEntryAggregationType::Billable)
                            <td style="border: 1px solid black;" data-type="{{ DataType::TYPE_STRING }}">
                                {{ $leafEntry['key'] ? 'Yes' : 'No' }}
                            </td>
                        @else
                            <td style="border: 1px solid black;" data-type="{{ DataType::TYPE_STRING }}">
                                {{ $leafEntry['description'] ?? $leafEntry['key'] ?? '-' }}
                            </td>
                        @endif
                    @endif
                    <td style="border: 1px solid black;" data-type="{{ DataType::TYPE_NUMERIC }}"
                        data-format="[hh]:mm:ss">
                        {{ $duration->totalDays }}
                    </td>
                    <td style="border: 1px solid black;" data-type="{{ DataType::TYPE_NUMERIC }}"
                        data-format="{{ NumberFormat::FORMAT_NUMBER_00 }}">
                        {{ $duration->totalHours }}
                    </td>
                    @if($showBillableRate)
                    <td style="border: 1px solid black;" data-type="{{ DataType::TYPE_NUMERIC }}"
                        data-format="{{ NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1 }}">
                        {{ BigDecimal::ofUnscaledValue($group2Entry['cost'], 2)->__toString() }}
                    </td>
                    @endif
                @endif
            </tr>
            @php
                ++$counter;
                $totalDuration += $leafEntry['seconds'];
                if ($showBillableRate) {
                    $totalCost += $leafEntry['cost'];
                }
            @endphp
            @endforeach
        @endforeach
    @endforeach
    @php
        $totalDurationInterval = CarbonInterval::seconds($totalDuration);
        $durationColumn = $hasThirdGroup ? 'D' : 'C';
        $decimalColumn = $hasThirdGroup ? 'E' : 'D';
        $amountColumn = $hasThirdGroup ? 'F' : 'E';
    @endphp
    <tr style="border: 1px solid black;">
        <td style="border: 1px solid black; font-weight: bold;" data-type="{{ DataType::TYPE_STRING }}"></td>
        @if($hasThirdGroup)
        <td style="border: 1px solid black; font-weight: bold;" data-type="{{ DataType::TYPE_STRING }}"></td>
        @endif
        <td style="border: 1px solid black; font-weight: bold;" data-type="{{ DataType::TYPE_STRING }}">
            Total
        </td>
        @if($exportFormat === ExportFormat::ODS || $exportFormat === ExportFormat::CSV)
            <td style="border: 1px solid black; font-weight: bold;" data-type="{{ DataType::TYPE_STRING }}">
                {{ $interval->format($totalDurationInterval) }}
            </td>
            <td style="border: 1px solid black; font-weight: bold;" data-type="{{ DataType::TYPE_STRING }}">
                {{ round($totalDurationInterval->totalHours, 2) }}
            </td>
            @if($showBillableRate)
            <td style="border: 1px solid black; font-weight: bold;" data-type="{{ DataType::TYPE_STRING }}">
                {{ round(BigDecimal::ofUnscaledValue($totalCost, 2)->toFloat(), 2) }}
            </td>
            @endif
        @else
            <td style="border: 1px solid black; font-weight: bold;" data-type="{{ DataType::TYPE_FORMULA }}"
                data-format="[hh]:mm:ss">
                @if($counter > 1)
                    =SUM({{ $durationColumn }}2:{{ $durationColumn }}{{ $counter }})
                @else
                    =0
                @endif
            </td>
            <td style="border: 1px solid black; font-weight: bold;" data-type="{{ DataType::TYPE_FORMULA }}"
                data-format="{{ NumberFormat::FORMAT_NUMBER_00 }}">
                @if($counter > 1)
                    =SUM({{ $decimalColumn }}2:{{ $decimalColumn }}{{ $counter }})
                @else
                    =0
                @endif
            </td>
            @if($showBillableRate)
                <td style="border: 1px solid black; font-weight: bold;" data-type="{{ DataType::TYPE_FORMULA }}"
                    data-format="{{ NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1 }}">
                    @if($counter > 1)
                        =SUM({{ $amountColumn }}2:{{ $amountColumn }}{{ $counter }})
                    @else
                        =0
                    @endif
                </td>
            @endif
        @endif
    </tr>
    </tbody>
</table>
