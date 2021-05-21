import {Component, OnInit} from '@angular/core';
import {DashboardService} from '../../services/dashboard.service';

@Component({
    selector: 'app-dashboard',
    templateUrl: './dashboard.component.html',
    styleUrls: ['./dashboard.component.sass']
})
export class DashboardComponent implements OnInit {

    constructor(public dashboardService: DashboardService) {
    }

    ngOnInit(): void {
    }

}
