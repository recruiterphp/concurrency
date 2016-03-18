(ns knossos-onebip.model-test
  (:require [clojure.test :refer :all]
            [knossos-onebip.model :refer :all]
            [knossos.model :refer :all]
            [knossos.op :refer :all]
            [clojure.pprint :refer [pprint]]))

(deftest free-state
  (let [lock (mongo-lock)]
    (is (= (mongo-lock :p1)
           (step lock (invoke :p1 :acquire nil))))
    (is (= (inconsistent "released a free lock")
           (step lock (invoke :p1 :release nil))))))

(deftest locked-state
  (let [lock (mongo-lock :p1)]
    (is (= (mongo-lock)
           (step lock (ok :p1 :release nil))))
    (is (= (inconsistent "released another process's lock")
           (step lock (ok :p2 :release nil))))
    (is (= (inconsistent "acquired a taken lock")
           (step lock (ok :p1 :acquire nil))))
    (is (= (inconsistent "acquired a taken lock")
           (step lock (ok :p2 :acquire nil))))))


; does this reorder the operations of a single process?
