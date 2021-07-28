import {Component, Inject, OnInit} from '@angular/core';
import {MAT_DIALOG_DATA, MatDialog, MatDialogRef} from '@angular/material/dialog';
import {SourceSelectorDialogComponent} from '../../dashboard-editor/source-selector-dialog/source-selector-dialog.component';
import {DashboardService} from '../../../services/dashboard.service';
import {DatasetNameDialogComponent} from '../../dataset/dataset-editor/dataset-name-dialog/dataset-name-dialog.component';
import * as _ from 'lodash';
import chroma from 'chroma-js';

@Component({
    selector: 'ki-configure-item',
    templateUrl: './configure-item.component.html',
    styleUrls: ['./configure-item.component.sass'],
    host: {class: 'configure-dialog'}
})
export class ConfigureItemComponent implements OnInit {

    public grid;
    public chartData: any;
    public metricData: any = {};
    public dashboard;
    public dashboardItemType;
    public dashboardDatasetInstance: any;
    public filterFields: any = [];
    public chartTypes = ['line', 'bar', 'pie', 'doughnut'];
    public metricFormats = ['Currency', 'Number', 'Percentage'];
    public currencies = [
        {
            name: 'British Pound (£)',
            value: 'GBP',
            symbol: '£'
        },
        {
            name: 'US Dollar ($)',
            value: 'USD',
            symbol: '$'
        },
        {
            name: 'Euro (€)',
            value: 'EUR',
            symbol: '€'
        }
    ];

    private dataset: any;

    constructor(public dialogRef: MatDialogRef<ConfigureItemComponent>,
                @Inject(MAT_DIALOG_DATA) public data: any,
                private dialog: MatDialog,
                private dashboardService: DashboardService) {
    }

    ngOnInit(): void {
        this.grid = this.data.grid;
        this.dashboard = this.data.dashboard;
        this.dashboardDatasetInstance = this.data.dashboardDatasetInstance;
        this.dashboardItemType = this.data.dashboardItemType;

        if (!this.dashboardDatasetInstance) {
            this.selectedDatasource();
        }

        if (this.dashboard.displaySettings &&
            this.dashboard.displaySettings.metric) {
            this.metricData = this.dashboard.displaySettings.metric[this.dashboardDatasetInstance.instanceKey] || {};
        }

    }

    public selectedDatasource() {
        const dialogRef = this.dialog.open(SourceSelectorDialogComponent, {
            width: '1200px',
            height: '800px',
            data: {
                dashboard: this.dashboard,
                dashboardDatasetInstance: this.dashboardDatasetInstance || {
                    dashboardId: this.dashboard.id,
                    instanceKey: this.data.itemInstanceKey,
                    transformationInstances: [],
                    parameterValues: {},
                    parameters: []
                }
            }
        });

        dialogRef.afterClosed().subscribe(dashboardDatasetInstance => {
            this.dashboardDatasetInstance = dashboardDatasetInstance;
        });
    }

    public dataLoaded(dataset) {
        this.dataset = dataset;
        this.filterFields = _.map(dataset.columns, column => {
            return {
                title: column.title,
                name: column.name
            };
        });
        this.updateMetricDataValues();
        this.setChartData();
    }

    public backgroundColourUpdate() {
        if (this.dashboardItemType.type === 'line') {
            this.dashboardItemType.backgroundColor = this.dashboardItemType.backgroundColorFrom || 'black';
        } else {
            this.dashboardItemType.backgroundColor = chroma.scale([
                this.dashboardItemType.backgroundColorFrom || 'black',
                this.dashboardItemType.backgroundColorTo || 'black']
            ).colors(this.dashboardItemType.labels.length);
        }
    }

    public saveDashboard() {
        const grid = this.grid.save(true);
        if (!this.dashboard.displaySettings) {
            this.dashboard.displaySettings = {};
        }
        this.dashboard.displaySettings.grid = grid;

        if (!this.dashboard.displaySettings.charts) {
            this.dashboard.displaySettings.charts = {};
        }

        this.dashboard.displaySettings.charts[this.dashboardDatasetInstance.instanceKey] = this.dashboardItemType;

        if (!this.dashboard.displaySettings.metric) {
            this.dashboard.displaySettings.metric = {};
        }

        this.dashboard.displaySettings.metric[this.dashboardDatasetInstance.instanceKey] = this.metricData;

        // If there is an old instance remove it, and then add the new/updated one.
        _.remove(this.dashboard.datasetInstances, {instanceKey: this.dashboardDatasetInstance.instanceKey});
        this.dashboard.datasetInstances.push(this.dashboardDatasetInstance);

        if (!this.dashboard.title) {
            const dialogRef = this.dialog.open(DatasetNameDialogComponent, {
                width: '475px',
                height: '150px',
            });
            dialogRef.afterClosed().subscribe(title => {
                this.dashboard.title = title;
                this.dashboardService.saveDashboard(this.dashboard);
                this.dialogRef.close();
            });
        } else {
            this.dashboardService.saveDashboard(this.dashboard);
            this.dialogRef.close(this.dashboardDatasetInstance);
        }
    }

    public setChartData() {
        if (this.dashboardItemType.xAxis && this.dashboardItemType.yAxis) {
            let data: any;

            if (this.dashboardItemType.type !== 'pie' && this.dashboardItemType.type !== 'doughnut') {
                data = _.map(this.dataset.allData, item => {
                    return {x: item[this.dashboardItemType.xAxis], y: item[this.dashboardItemType.yAxis]};
                });
            } else {
                data = _.map(this.dataset.allData, item => {
                    return item[this.dashboardItemType.yAxis];
                });
            }

            this.chartData = [
                {
                    data,
                    label: _.find(this.filterFields, {name: this.dashboardItemType.xAxis}).title,
                    fill: !!this.dashboardItemType.fill
                }
            ];
            this.dashboardItemType.labels = _.map(this.dataset.allData, item => {
                return item[this.dashboardItemType.xAxis];
            });
        }
    }

    public updateMetricDataValues() {
        if (this.metricData.main) {
            this.metricData.mainValue = this.dataset.allData[0][this.metricData.main];
        }

        if (this.metricData.mainFormat) {
            if (this.metricData.mainFormatDecimals) {
                this.metricData.mainValue = Number(this.metricData.mainValue).toFixed(this.metricData.mainFormatDecimals);
            }
            if (this.metricData.mainFormat === 'Currency' && this.metricData.mainFormatCurrency) {
                const currency = _.find(this.currencies, {value: this.metricData.mainFormatCurrency});
                if (currency) {
                    this.metricData.mainValue = currency.symbol + '' + this.metricData.mainValue;
                }
            }
            if (this.metricData.mainFormat === 'Percentage') {
                this.metricData.mainValue = this.metricData.mainValue + '%';
            }
        }

        if (this.metricData.subMetric) {
            this.metricData.subValue = this.dataset.allData[0][this.metricData.subMetric];
        }

        if (this.metricData.subMetricFormat) {
            if (this.metricData.subMetricFormatDecimals) {
                this.metricData.subValue = Number(this.metricData.subValue).toFixed(this.metricData.subMetricFormatDecimals);
            }
            if (this.metricData.subMetricFormat === 'Currency' && this.metricData.subMetricFormatCurrency) {
                const currency = _.find(this.currencies, {value: this.metricData.subMetricFormatCurrency});
                if (currency) {
                    this.metricData.subValue = currency.symbol + '' + this.metricData.subValue;
                }
            }
            if (this.metricData.subMetricFormat === 'Percentage') {
                this.metricData.subValue = this.metricData.subValue + '%';
            }
        }

        if (this.metricData.showSubChange) {
            const changeClass = `${parseInt(this.metricData.subValue, 10) > 0 ? 'up' : 'down'}`;
            const icon = `${parseInt(this.metricData.subValue, 10) > 0 ? '&#8593;' : '&#8595;'}`;
            this.metricData.subValue = `<span class="sub-change ${changeClass}">${icon}&nbsp;${this.metricData.subValue}</span>`;
        }
    }
}
