import {Component, Inject, Input, OnInit} from '@angular/core';
import {MAT_DIALOG_DATA, MatDialog, MatDialogRef} from '@angular/material/dialog';
import {DatasetNameDialogComponent} from '../dataset/dataset-editor/dataset-name-dialog/dataset-name-dialog.component';

@Component({
    selector: 'ki-data-explorer',
    templateUrl: './data-explorer.component.html',
    styleUrls: ['./data-explorer.component.sass'],
    host: {class: 'configure-dialog'}
})
export class DataExplorerComponent implements OnInit {

    public showChart = false;
    public chartData;
    public datasource: any;
    public dataset: any;
    public datasetInstance: any;
    public filters: any;
    public datasetService: any;
    public datasourceService: any;

    constructor(public dialogRef: MatDialogRef<DataExplorerComponent>,
                @Inject(MAT_DIALOG_DATA) public data: any,
                private dialog: MatDialog) {
    }

    ngOnInit(): void {
        this.chartData = !!this.data.showChart;
        this.datasource = this.data.datasource;
        this.datasetInstance = this.data.dataset;
        this.datasetService = this.data.datasetService;
        this.datasourceService = this.data.datasourceService;

        this.chartData = [
            {data: [1000, 1400, 1999, 2500, 5000]},
        ];

    }

    public dataLoaded(data) {
        console.log('Data loaded', data);
    }

    public saveChanges() {
        if (!this.datasetInstance.title) {
            const dialogRef = this.dialog.open(DatasetNameDialogComponent, {
                width: '400px',
                height: '200px',
            });
            dialogRef.afterClosed().subscribe(res => {
                if (res) {
                    this.datasetInstance.title = res;
                    this.datasetService.saveDataset(this.datasetInstance).then(() => {
                        this.dialogRef.close();
                    });
                }
            });
        }

    }

}
