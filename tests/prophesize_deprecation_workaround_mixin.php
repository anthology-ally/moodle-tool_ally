<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_ally;

use PHPUnit\Framework\Exception;
use Prophecy\Prophecy\ObjectProphecy;
use Prophecy\Prophet;
use ReflectionMethod;

trait prophesize_deprecation_workaround_mixin {
    /**
     * Workaround for prophesize() being deprecated in the version defined in Moodle's composer.json.
     * @throws ReflectionException
     */
    public function prophesize_without_deprecation_warning(?string $classorinterface = null): ObjectProphecy {
        if (!class_exists(Prophet::class)) {
            throw new Exception('This test uses TestCase::prophesize(), but phpspec/prophecy is not installed. Please run "composer require --dev phpspec/prophecy".');
        }

        if (is_string($classorinterface)) {
            $this->recordDoubledType($classorinterface);
        }

        // Can't call $this->getProphet() or access $this->prophet due to private scope, call with reflection.
        $method = new ReflectionMethod($this, "getProphet");
        $method->setAccessible(true);

        /** @var Prophet $prophet */
        $prophet = $method->invoke($this);

        return $prophet->prophesize($classorinterface);
    }
}
