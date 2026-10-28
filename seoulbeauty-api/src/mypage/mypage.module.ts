import { Module } from '@nestjs/common';
import { PointsModule } from '../points/points.module';
import { MypageController } from './mypage.controller';
import { MypageService } from './mypage.service';

@Module({ imports: [PointsModule], controllers: [MypageController], providers: [MypageService] })
export class MypageModule {}
